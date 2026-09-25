<?php

declare(strict_types=1);

namespace Yawasla\Entity;

use Yawasla\Core\Model;

final class Organization extends Model
{
    public ?int $id = null;
    public string $name;
    public ?string $address = null;
    public ?string $city = null;
    public ?string $postal_code = null;
    public ?string $phone = null;
    public ?string $email = null;
    public ?string $domain = null;

    public static function table(): string
    {
        return 'organizations';
    }

    public static function fillable(): array
    {
        return ['name', 'address', 'city', 'postal_code', 'phone', 'email', 'domain'];
    }

    public static function forDomain(string $domain): ?static
    {
        return static::first(['domain' => $domain]) ?? static::first(['ORDER' => ['id' => 'ASC']]);
    }
}
