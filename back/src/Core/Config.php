<?php

declare(strict_types=1);

namespace Yawasla\Core;

final class Config
{
    public string $appEnv;
    /** URL publique de l'app (ex. https://beta.yawasla.org), pour les liens absolus des mails */
    public string $appUrl;
    public string $dbConnection;
    public string $dbHost;
    public int $dbPort;
    public string $dbDatabase;
    public string $dbUsername;
    public string $dbPassword;
    public string $dbCharset;
    /** DSN SMTP sur une ligne (ex. smtps://user:pass@host:465), vide = fonction mail() native de PHP */
    public string $smtp;
    public string $mailFromAddress;
    public string $mailFromName;
    /** version.json des releases (voir Updater), vide = vérification des mises à jour désactivée */
    public string $updateUrl;

    public function __construct(array $env)
    {
        $this->appEnv = $env['APP_ENV'] ?? 'prod';
        $this->appUrl = rtrim($env['APP_URL'] ?? '', '/');
        $this->dbConnection = $env['DB_CONNECTION'] ?? 'mysql';
        $this->dbHost = $env['DB_HOST'] ?? '127.0.0.1';
        $this->dbPort = (int) ($env['DB_PORT'] ?? 3306);
        $this->dbDatabase = $env['DB_DATABASE'] ?? '';
        $this->dbUsername = $env['DB_USERNAME'] ?? '';
        $this->dbPassword = $env['DB_PASSWORD'] ?? '';
        $this->dbCharset = $env['DB_CHARSET'] ?? 'utf8mb4';
        $this->smtp = $env['MAIL_SMTP'] ?? '';
        $this->mailFromAddress = $env['MAIL_FROM_ADDRESS'] ?? 'noreply@yawasla.org';
        $this->mailFromName = $env['MAIL_FROM_NAME'] ?? 'Yawasla';
        $this->updateUrl = $env['UPDATE_URL'] ?? 'https://dist.yawasla.org/version.json';
    }

    public function isDev(): bool
    {
        return $this->appEnv === 'dev';
    }
}
