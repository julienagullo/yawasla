<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Medoo\Medoo;
use PDO;
use PDOException;
use RuntimeException;

final class Migrator
{
    /**
     * Version virtuelle d'une base jamais installée : aucune ligne en base ne la porte, elle
     * résulte de l'absence de la table "version" (ou de sa vidange) et permet de traiter une
     * installation fraîche comme une mise à jour depuis la version 0.
     */
    public const NOT_INSTALLED = '0.0.0';

    /** Tables dont le contenu prouve qu'un site est déjà installé et utilisé. */
    private const DATA_TABLES = ['organizations', 'users'];

    public function __construct(
        private readonly Medoo $connection,
        private readonly string $migrationsPath,
    ) {
    }

    public function currentVersion(): string
    {
        // Pas de vérification préalable de l'existence de la table (une requête de plus à chaque
        // requête HTTP) : on tente la lecture et on n'analyse la base qu'en cas d'échec.
        try {
            $version = $this->connection->get('version', 'version', ['ORDER' => ['id' => 'DESC']]);
        } catch (PDOException $exception) {
            if (in_array('version', $this->tables(), true)) {
                throw $exception;
            }

            $version = null;
        }

        if ($version !== null) {
            return $version;
        }

        // Pas de version connue. Une installation interrompue (tables créées, ligne "version" non
        // écrite) est rejouable car les migrations utilisent IF NOT EXISTS ; en revanche, des données
        // déjà présentes signalent un site existant dont la table "version" a disparu.
        if ($this->hasData()) {
            throw new RuntimeException(
                'État incohérent : la table "version" est absente ou vide alors que des données existent déjà '
                . 'en base. Installation interrompue par sécurité — vérifier la table "version" manuellement.'
            );
        }

        return self::NOT_INSTALLED;
    }

    /**
     * @return list<string> versions des migrations en attente jusqu'à $targetVersion, par ordre croissant
     */
    public function pendingVersions(string $targetVersion): array
    {
        return array_keys($this->pendingMigrations($targetVersion));
    }

    public function migrateTo(string $targetVersion): void
    {
        $this->ensureVersionTable();

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

    private function ensureVersionTable(): void
    {
        $this->connection->create('version', [
            '@id',
            'version' => ['VARCHAR(20)', 'NOT NULL'],
            'installed_at' => ['DATETIME', 'NOT NULL'],
        ], Schema::tableOptions($this->connection));
    }

    /** @return list<string> */
    private function tables(): array
    {
        $statement = match ($this->connection->type) {
            'sqlite' => $this->connection->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite\_%' ESCAPE '\\'"
            ),
            default => $this->connection->query('SHOW TABLES'),
        };

        return $statement?->fetchAll(PDO::FETCH_COLUMN) ?? [];
    }

    private function hasData(): bool
    {
        $tables = $this->tables();

        foreach (self::DATA_TABLES as $table) {
            if (in_array($table, $tables, true) && $this->connection->count($table) > 0) {
                return true;
            }
        }

        return false;
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
