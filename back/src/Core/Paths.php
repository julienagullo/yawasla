<?php

declare(strict_types=1);

namespace Yawasla\Core;

use RuntimeException;

/**
 * Emplacements des fichiers de l'application. Les dossiers de var/{APP_ENV}/ (logs, cache de
 * routes, sessions, fichier sqlite) sont créés à la première demande, jamais commités.
 */
final class Paths
{
    public function __construct(public string $root, private string $appEnv)
    {
    }

    public function envFile(): string
    {
        return $this->root . '/.env';
    }

    public function migrations(): string
    {
        return $this->root . '/database/migrations';
    }

    /** index.html compilé par Vite, présent uniquement dans la distribution (voir AppShell). */
    public function shellTemplate(): string
    {
        return $this->root . '/resources/app.html';
    }

    public function logsDir(): string
    {
        return $this->ensure($this->root . '/var/' . $this->appEnv . '/logs');
    }

    public function cacheDir(): string
    {
        return $this->ensure($this->root . '/var/' . $this->appEnv . '/cache');
    }

    public function databaseDir(): string
    {
        return $this->ensure($this->root . '/var/' . $this->appEnv . '/db');
    }

    /** Réservé au propriétaire : un fichier de session lisible = session volable. */
    public function sessionsDir(): string
    {
        return $this->ensure($this->root . '/var/' . $this->appEnv . '/sessions', 0700);
    }

    /** État transitoire de sécurité (jetons de reset, tentatives de connexion…), réservé au propriétaire. */
    public function tempDir(): string
    {
        return $this->ensure($this->root . '/var/' . $this->appEnv . '/temp', 0700);
    }

    private function ensure(string $dir, int $mode = 0775): string
    {
        if (!is_dir($dir) && !mkdir($dir, $mode, true) && !is_dir($dir)) {
            throw new RuntimeException("Impossible de créer le dossier \"{$dir}\".");
        }

        return $dir;
    }
}
