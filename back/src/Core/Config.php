<?php

declare(strict_types=1);

namespace Yawasla\Core;

final class Config
{
    public readonly string $appEnv;
    public readonly string $dbConnection;
    public readonly string $dbHost;
    public readonly int $dbPort;
    public readonly string $dbDatabase;
    public readonly string $dbUsername;
    public readonly string $dbPassword;
    public readonly string $dbCharset;

    public function __construct(array $env)
    {
        $this->appEnv = $env['APP_ENV'] ?? 'prod';
        $this->dbConnection = $env['DB_CONNECTION'] ?? 'mysql';
        $this->dbHost = $env['DB_HOST'] ?? '127.0.0.1';
        $this->dbPort = (int) ($env['DB_PORT'] ?? 3306);
        $this->dbDatabase = $env['DB_DATABASE'] ?? '';
        $this->dbUsername = $env['DB_USERNAME'] ?? '';
        $this->dbPassword = $env['DB_PASSWORD'] ?? '';
        $this->dbCharset = $env['DB_CHARSET'] ?? 'utf8mb4';
    }

    public function isDev(): bool
    {
        return $this->appEnv === 'dev';
    }
}
