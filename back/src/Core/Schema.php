<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Medoo\Medoo;

final class Schema
{
    // utf8mb4_unicode_ci plutôt que utf8mb4_0900_ai_ci (défaut MySQL 8) : cette dernière
    // n'existe pas sous MariaDB, alors que unicode_ci est commune aux deux moteurs.
    public const CHARSET = 'utf8mb4';
    public const COLLATION = 'utf8mb4_unicode_ci';

    /**
     * Options de table à passer en 3e argument de Medoo::create(), pour que le jeu de
     * caractères des tables ne dépende jamais de celui (parfois mauvais) de la base.
     * Null hors MySQL/MariaDB : sqlite n'a pas de notion de charset/collation de table.
     */
    public static function tableOptions(Medoo $db): ?string
    {
        if ($db->type !== 'mysql') {
            return null;
        }

        return 'DEFAULT CHARSET=' . self::CHARSET . ' COLLATE=' . self::COLLATION;
    }
}
