<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Medoo\Medoo;
use Throwable;

final class DatabaseConnector
{
    /**
     * Connexion à la base configurée dans le .env.
     *
     * @throws UnknownDatabaseException le serveur MySQL répond mais la base n'existe pas
     * @throws Throwable toute autre erreur de connexion (serveur injoignable, identifiants…)
     */
    public static function connect(Config $config, Paths $paths): Medoo
    {
        if ($config->dbConnection === 'sqlite') {
            $database = new Medoo([
                'type' => 'sqlite',
                'database' => $paths->databaseDir() . '/' . $config->dbDatabase,
            ]);

            // Contrairement à MySQL, sqlite n'applique pas les contraintes FOREIGN KEY par défaut :
            // sans ce pragma, elles existent dans le schéma mais ne sont jamais vérifiées.
            $database->pdo->exec('PRAGMA foreign_keys = ON');

            return $database;
        }

        $options = [
            'type' => $config->dbConnection,
            'port' => $config->dbPort,
            'database' => $config->dbDatabase,
            'username' => $config->dbUsername,
            'password' => $config->dbPassword,
            'charset' => $config->dbCharset,
        ];

        $hosts = $config->dbConnection === 'mysql'
            ? DatabaseSetup::hostCandidates($config->dbHost)
            : [$config->dbHost];

        foreach ($hosts as $index => $host) {
            try {
                return new Medoo(['host' => $host] + $options);
            } catch (Throwable $exception) {
                // "localhost" injoignable (socket Unix introuvable) : on retente en TCP
                if ($index < count($hosts) - 1 && DatabaseSetup::isUnreachable($exception)) {
                    continue;
                }

                if ($config->dbConnection === 'mysql' && DatabaseSetup::isUnknownDatabase($exception)) {
                    throw new UnknownDatabaseException($exception->getMessage(), 0, $exception);
                }

                throw $exception;
            }
        }

        // Inatteignable : la boucle retourne ou lève toujours
        throw new UnknownDatabaseException('Aucun hôte de base de données configuré.');
    }
}
