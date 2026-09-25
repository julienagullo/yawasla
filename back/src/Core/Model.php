<?php

declare(strict_types=1);

namespace Yawasla\Core;

use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use Medoo\Medoo;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;

abstract class Model
{
    private static ?Medoo $connection = null;

    /**
     * Colonnes de chaque entité : ses propriétés publiques non statiques, indexées par nom.
     *
     * @var array<class-string, array<string, ReflectionProperty>>
     */
    private static array $columns = [];

    protected bool $exists = false;

    abstract public static function table(): string;

    abstract public static function fillable(): array;

    public static function primaryKey(): string
    {
        return 'id';
    }

    public static function setConnection(Medoo $connection): void
    {
        self::$connection = $connection;
    }

    protected static function connection(): Medoo
    {
        if (self::$connection === null) {
            throw new RuntimeException(
                'Aucune connexion à la base de données n\'a été configurée (Model::setConnection).'
            );
        }

        return self::$connection;
    }

    // Sans ces garde-fous, PHP < 8.2 crée silencieusement une propriété dynamique sur une faute de frappe
    public function __get(string $name): mixed
    {
        throw new LogicException(sprintf('Propriété inconnue : %s::$%s.', static::class, $name));
    }

    public function __set(string $name, mixed $value): void
    {
        throw new LogicException(sprintf('Propriété inconnue : %s::$%s.', static::class, $name));
    }

    public function fill(array $data): static
    {
        $columns = static::columns();

        foreach (static::fillable() as $field) {
            if (array_key_exists($field, $data)) {
                $this->{$field} = static::cast($columns[$field], $data[$field]);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        $data = [];

        foreach (static::columns() as $name => $property) {
            if ($property->isInitialized($this)) {
                $value = $this->{$name};
                $data[$name] = $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : $value;
            }
        }

        return $data;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public static function find(int|string $id): ?static
    {
        $cacheKey = static::cacheKey($id);

        if (function_exists('apcu_fetch')) {
            $found = false;
            $cached = apcu_fetch($cacheKey, $found);

            if ($found) {
                return $cached;
            }
        }

        $row = static::connection()->get(static::table(), '*', [static::primaryKey() => $id]);

        if (!is_array($row)) {
            return null;
        }

        $instance = static::hydrate($row);

        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, $instance);
        }

        return $instance;
    }

    /**
     * @return static[]
     */
    public static function all(array $where = []): array
    {
        $rows = static::connection()->select(static::table(), '*', $where) ?? [];

        return array_map(static fn (array $row): static => static::hydrate($row), $rows);
    }

    public static function first(array $where): ?static
    {
        $row = static::connection()->get(static::table(), '*', $where);

        return is_array($row) ? static::hydrate($row) : null;
    }

    public static function count(array $where = []): int
    {
        return (int) static::connection()->count(static::table(), $where);
    }

    public function save(): bool
    {
        $primaryKey = static::primaryKey();

        $data = $this->toDatabase();
        unset($data[$primaryKey]);

        if ($this->exists) {
            $statement = static::connection()->update(static::table(), $data, [$primaryKey => $this->{$primaryKey}]);
        } else {
            $statement = static::connection()->insert(static::table(), $data);

            if ($statement === null) {
                return false;
            }

            $this->{$primaryKey} = static::cast(static::columns()[$primaryKey], static::connection()->id());
            $this->exists = true;
        }

        static::forgetCache($this->{$primaryKey});

        return $statement !== null;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $primaryKey = static::primaryKey();
        $id = $this->{$primaryKey};

        $statement = static::connection()->delete(static::table(), [$primaryKey => $id]);

        static::forgetCache($id);
        $this->exists = false;

        return $statement !== null;
    }

    /**
     * @return array{data: static[], total: int, page: int, per_page: int}
     */
    public static function paginate(int $page, int $perPage, array $where = []): array
    {
        $total = static::count($where);

        $query = $where;
        $query['LIMIT'] = [($page - 1) * $perPage, $perPage];

        $rows = static::connection()->select(static::table(), '*', $query) ?? [];

        return [
            'data' => array_map(static fn (array $row): static => static::hydrate($row), $rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    protected static function hydrate(array $row): static
    {
        $instance = new static();

        foreach (static::columns() as $name => $property) {
            if (array_key_exists($name, $row)) {
                $instance->{$name} = static::cast($property, $row[$name]);
            }
        }

        $instance->exists = true;

        return $instance;
    }

    /**
     * @return array<string, ReflectionProperty>
     */
    protected static function columns(): array
    {
        if (!isset(self::$columns[static::class])) {
            self::$columns[static::class] = [];

            foreach ((new ReflectionClass(static::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if (!$property->isStatic()) {
                    self::$columns[static::class][$property->getName()] = $property;
                }
            }
        }

        return self::$columns[static::class];
    }

    /**
     * Convertit une valeur brute (ligne Medoo, données de formulaire) vers le type déclaré de la propriété.
     */
    private static function cast(ReflectionProperty $property, mixed $value): mixed
    {
        $type = $property->getType();

        if ($value === null || !$type instanceof ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            DateTimeImmutable::class => $value instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($value)
                : new DateTimeImmutable((string) $value),
            default => $value,
        };
    }

    /**
     * Valeurs à écrire en base. Une propriété non initialisée correspond à une colonne NOT NULL sans défaut.
     */
    private function toDatabase(): array
    {
        $data = [];

        foreach (static::columns() as $name => $property) {
            if (!$property->isInitialized($this)) {
                throw new LogicException(sprintf('%s::$%s n\'est pas renseigné.', static::class, $name));
            }

            $value = $this->{$name};

            $data[$name] = match (true) {
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                is_bool($value) => (int) $value,
                default => $value,
            };
        }

        return $data;
    }

    private static function cacheKey(int|string $id): string
    {
        return 'model:' . static::class . ':' . $id;
    }

    private static function forgetCache(int|string $id): void
    {
        if (function_exists('apcu_delete')) {
            apcu_delete(static::cacheKey($id));
        }
    }
}
