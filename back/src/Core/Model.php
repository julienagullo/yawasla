<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Medoo\Medoo;
use RuntimeException;

abstract class Model
{
    private static ?Medoo $connection = null;

    protected array $attributes = [];

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

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function fill(array $data): static
    {
        foreach (static::fillable() as $field) {
            if (array_key_exists($field, $data)) {
                $this->attributes[$field] = $data[$field];
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return $this->attributes;
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

        if ($this->exists) {
            $id = $this->attributes[$primaryKey];
            $data = $this->attributes;
            unset($data[$primaryKey]);

            $statement = static::connection()->update(static::table(), $data, [$primaryKey => $id]);
        } else {
            $statement = static::connection()->insert(static::table(), $this->attributes);

            if ($statement === null) {
                return false;
            }

            $this->attributes[$primaryKey] = (int) static::connection()->id();
            $this->exists = true;
        }

        static::forgetCache($this->attributes[$primaryKey]);

        return $statement !== null;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $primaryKey = static::primaryKey();
        $id = $this->attributes[$primaryKey];

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
        $instance->attributes = $row;
        $instance->exists = true;

        return $instance;
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
