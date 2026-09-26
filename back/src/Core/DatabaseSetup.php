<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Medoo\Medoo;
use PDO;
use PDOException;
use Throwable;

/**
 * Préparation de la base de données par l'assistant d'installation : teste la connexion,
 * crée la base si besoin (avec le bon jeu de caractères) et écrit le fichier .env.
 */
final class DatabaseSetup
{
    public function __construct(private string $envPath)
    {
    }

    public function hasEnvFile(): bool
    {
        return is_file($this->envPath);
    }

    /**
     * Hôtes à essayer dans l'ordre. Avec "localhost", PDO passe par un socket Unix dont le chemin
     * dépend du php.ini (souvent faux sous MAMP/Homebrew) : en cas d'échec (erreur 2002),
     * on retente en TCP sur 127.0.0.1.
     *
     * @return list<string>
     */
    public static function hostCandidates(string $host): array
    {
        return strtolower($host) === 'localhost' ? [$host, '127.0.0.1'] : [$host];
    }

    /** Erreur MySQL 2002 : serveur ou socket injoignable. */
    public static function isUnreachable(Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), '[2002]');
    }

    /** Erreur MySQL 1049 : le serveur répond mais la base demandée n'existe pas. */
    public static function isUnknownDatabase(Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), '[1049]');
    }

    /**
     * Vérifie la connexion au serveur, puis crée la base si elle n'existe pas.
     *
     * @param array{host: string, port: int, database: string, username: string, password: string} $credentials
     * @return bool true si la base a été créée
     * @throws ApiException
     */
    public function prepareDatabase(array $credentials): bool
    {
        $database = $credentials['database'];

        if (preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $database) !== 1) {
            throw new ApiException(
                'database.invalid_name',
                'Le nom de la base ne peut contenir que des lettres, chiffres, "_", "-" et "$" (64 caractères maximum).',
            );
        }

        $pdo = null;
        $lastException = null;

        foreach (self::hostCandidates($credentials['host']) as $host) {
            try {
                $pdo = new PDO(
                    sprintf('mysql:host=%s;port=%d;charset=%s', $host, $credentials['port'], Schema::CHARSET),
                    $credentials['username'],
                    $credentials['password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                );
                break;
            } catch (PDOException $exception) {
                if (!self::isUnreachable($exception)) {
                    throw $this->connectionError($exception);
                }
                $lastException = $exception;
            }
        }

        if ($pdo === null) {
            throw $this->connectionError($lastException);
        }

        $statement = $pdo->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->execute([$database]);

        if ($statement->fetchColumn() !== false) {
            return false;
        }

        try {
            $pdo->exec(sprintf(
                'CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s',
                $database,
                Schema::CHARSET,
                Schema::COLLATION,
            ));
        } catch (PDOException $exception) {
            throw new ApiException(
                'database.create_failed',
                "Impossible de créer la base de données « {$database} », veuillez vérifier les droits utilisateurs.",
                ['database' => $database],
                422,
                $exception,
            );
        }

        return true;
    }

    /**
     * Écrit le fichier .env (lecture/écriture limitées au propriétaire).
     *
     * @param array{host: string, port: int, database: string, username: string, password: string} $credentials
     */
    public function writeEnv(array $credentials): void
    {
        $values = [
            'APP_ENV' => 'prod',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $credentials['host'],
            'DB_PORT' => (string) $credentials['port'],
            'DB_DATABASE' => $credentials['database'],
            'DB_USERNAME' => $credentials['username'],
            'DB_PASSWORD' => $credentials['password'],
            'DB_CHARSET' => Schema::CHARSET,
        ];

        $lines = [];
        foreach ($values as $key => $value) {
            // Guillemets doubles : \, " et $ échappés (sinon phpdotenv interpole ${VAR} et lit \n comme un retour à la ligne)
            $lines[] = $key . '="' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
        }

        $written = @file_put_contents($this->envPath, implode("\n", $lines) . "\n", LOCK_EX);

        if ($written === false) {
            throw new ApiException(
                'database.env_not_writable',
                'Impossible d\'écrire le fichier .env : vérifiez les droits d\'écriture du dossier "back".',
            );
        }

        @chmod($this->envPath, 0600);
    }

    /**
     * Aligne le jeu de caractères par défaut de la base sur utf8mb4 (MySQL/MariaDB uniquement).
     * Non bloquant : les tables portent de toute façon leur propre collation (voir Schema).
     *
     * @return bool false si la base n'a pas pu être alignée (droits insuffisants)
     */
    public static function alignCharset(Medoo $db): bool
    {
        if ($db->type !== 'mysql') {
            return true;
        }

        $charset = $db->query(
            'SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()'
        )?->fetchColumn();

        if ($charset === Schema::CHARSET) {
            return true;
        }

        try {
            $db->pdo->exec(sprintf('ALTER DATABASE CHARACTER SET %s COLLATE %s', Schema::CHARSET, Schema::COLLATION));
        } catch (PDOException) {
            return false;
        }

        return true;
    }

    private function connectionError(PDOException $exception): ApiException
    {
        $message = $exception->getMessage();

        [$code, $text] = match (true) {
            str_contains($message, '[1045]') => ['database.access_denied', 'Identifiants incorrects : utilisateur ou mot de passe refusé par le serveur.'],
            str_contains($message, '[2002]'), str_contains($message, '[2006]') => ['database.unreachable', 'Serveur de base de données injoignable : vérifiez l\'hôte et le port.'],
            str_contains($message, '[1044]') => ['database.forbidden', 'Cet utilisateur n\'a pas accès à cette base.'],
            default => ['database.connection_failed', 'Connexion à la base de données impossible.'],
        };

        return new ApiException($code, $text, [], 422, $exception);
    }
}
