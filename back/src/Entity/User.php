<?php

declare(strict_types=1);

namespace Yawasla\Entity;

use Yawasla\Core\Model;

final class User extends Model
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    public static function table(): string
    {
        return 'users';
    }

    public static function fillable(): array
    {
        return ['organization_id', 'first_name', 'last_name', 'display_name', 'email', 'password', 'role'];
    }
}
