<?php

declare(strict_types=1);

namespace Yawasla\Entity;

use Yawasla\Core\Model;

final class Organization extends Model
{
    public static function table(): string
    {
        return 'organizations';
    }

    public static function fillable(): array
    {
        return ['nom', 'adresse', 'ville', 'code_postal', 'telephone', 'email', 'domain'];
    }

    public static function forDomain(string $domain): ?static
    {
        return static::first(['domain' => $domain]) ?? static::first(['ORDER' => ['id' => 'ASC']]);
    }
}
