<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;

final class Container implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }

        if (class_exists($id)) {
            return $this->instances[$id] = $this->autowire($id);
        }

        throw new ContainerNotFoundException("Aucun service enregistré pour \"{$id}\".");
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || isset($this->factories[$id])
            || class_exists($id);
    }

    private function autowire(string $class): object
    {
        try {
            $reflection = new ReflectionClass($class);
        } catch (Throwable $exception) {
            throw new ContainerNotFoundException("Classe \"{$class}\" introuvable.", previous: $exception);
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(
                "\"{$class}\" n'est pas instanciable (interface ou classe abstraite non enregistrée explicitement)."
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->get($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new ContainerException(
                "Impossible de résoudre automatiquement le paramètre \"{$parameter->getName()}\" de \"{$class}\" (type scalaire sans valeur par défaut)."
            );
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
