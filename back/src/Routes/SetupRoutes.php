<?php

declare(strict_types=1);

namespace Yawasla\Routes;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Core\ApiException;
use Yawasla\Core\AppState;
use Yawasla\Core\Config;
use Yawasla\Core\DatabaseSetup;
use Yawasla\Http\Json;

/**
 * État "config_required" : la base n'est pas utilisable.
 *  - "no_env" : pas de fichier .env, l'assistant recueille les identifiants et l'écrit ;
 *  - "unknown_database" : .env présent mais la base n'existe pas, on la crée avec ses identifiants.
 */
final class SetupRoutes implements RouteProvider
{
    public function __construct(private AppState $state, private Config $config, private DatabaseSetup $setup)
    {
    }

    public function register(RouteCollectorProxyInterface $api): void
    {
        $api->get('/', [$this, 'status']);
        $api->post('/install/database', [$this, 'configureDatabase']);
    }

    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = ['status' => AppState::CONFIG_REQUIRED, 'reason' => $this->state->reason, 'target_version' => APP_VERSION];

        if ($this->state->reason === 'unknown_database') {
            $payload['database'] = $this->config->dbDatabase;
        }

        return Json::respond($response, $payload);
    }

    public function configureDatabase(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $credentials = $this->state->reason === 'unknown_database'
            ? $this->configuredCredentials()
            : $this->submittedCredentials((array) $request->getParsedBody());

        // Erreurs affichables levées en ApiException, toute autre erreur (PDO…) reste une 500
        $created = $this->setup->prepareDatabase($credentials);

        if ($this->state->reason === 'no_env') {
            $this->setup->writeEnv($credentials);
        }

        return Json::respond($response, ['status' => 'configured', 'created' => $created]);
    }

    /** @return array{host: string, port: int, database: string, username: string, password: string} */
    private function configuredCredentials(): array
    {
        return [
            'host' => $this->config->dbHost,
            'port' => $this->config->dbPort,
            'database' => $this->config->dbDatabase,
            'username' => $this->config->dbUsername,
            'password' => $this->config->dbPassword,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    private function submittedCredentials(array $data): array
    {
        $credentials = [
            'host' => trim((string) ($data['host'] ?? '')),
            'port' => (int) ($data['port'] ?? 3306),
            'database' => trim((string) ($data['database'] ?? '')),
            'username' => (string) ($data['username'] ?? ''),
            'password' => (string) ($data['password'] ?? ''),
        ];

        if ($credentials['host'] === '' || $credentials['database'] === '' || $credentials['username'] === '') {
            throw new ApiException('database.required_fields', 'L\'hôte, le nom de la base et l\'utilisateur sont obligatoires.');
        }

        if ($credentials['port'] < 1 || $credentials['port'] > 65535) {
            throw new ApiException('database.invalid_port', 'Port invalide.');
        }

        if (preg_match('/[\r\n\0]/', implode('', $credentials)) === 1) {
            throw new ApiException('database.invalid_characters', 'Caractères invalides dans les identifiants.');
        }

        return $credentials;
    }
}
