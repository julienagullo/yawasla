<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Medoo\Medoo;
use PDO;
use RuntimeException;

final class Migrator
{
    public function __construct(
        private readonly Medoo $connection,
        private readonly string $migrationsPath,
    ) {
        $this->connection->create('version', [
            '@id',
            'version' => ['VARCHAR(20)', 'NOT NULL'],
            'installed_at' => ['DATETIME', 'NOT NULL'],
        ]);
    }

    public function currentVersion(): string
    {
        $version = $this->connection->get('version', 'version', ['ORDER' => ['id' => 'DESC']]);

        if ($version !== null) {
            return $version;
        }

        if ($this->hasOtherTables()) {
            throw new RuntimeException(
                'État incohérent : la table "version" est vide mais d\'autres tables existent déjà en base. '
                . 'Installation interrompue par sécurité — vérifier la table "version" manuellement.'
            );
        }

        return '0.0.0';
    }

    public function migrateTo(string $targetVersion): void
    {
        foreach ($this->pendingMigrations($targetVersion) as $version => $file) {
            $migrate = require $file;
            $migrate($this->connection);

            $this->connection->insert('version', [
                'version' => $version,
                'installed_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @return array<string, string> version => chemin du fichier, trié par version croissante
     */
    private function pendingMigrations(string $targetVersion): array
    {
        $currentVersion = $this->currentVersion();
        $pending = [];

        foreach (glob($this->migrationsPath . '/*.php') ?: [] as $file) {
            $version = $this->parseVersion(basename($file));

            if (
                version_compare($version, $currentVersion, '>')
                && version_compare($version, $targetVersion, '<=')
            ) {
                $pending[$version] = $file;
            }
        }

        uksort($pending, 'version_compare');

        return $pending;
    }

    private function hasOtherTables(): bool
    {
        $statement = match ($this->connection->type) {
            'sqlite' => $this->connection->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite\_%' ESCAPE '\\'"
            ),
            default => $this->connection->query('SHOW TABLES'),
        };

        $tables = $statement?->fetchAll(PDO::FETCH_COLUMN) ?? [];

        return array_diff($tables, ['version']) !== [];
    }

    private function parseVersion(string $filename): string
    {
        if (preg_match('/^\d{8}-(\d+\.\d+\.\d+)\.php$/', $filename, $matches) !== 1) {
            throw new RuntimeException(
                "Nom de fichier de migration invalide : \"{$filename}\" (attendu : AAAAMMJJ-x.y.z.php)."
            );
        }

        return $matches[1];
    }
}
