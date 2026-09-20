<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Core\Config;
use Yawasla\Core\DatabaseSetup;

/**
 * Routes de l'état "config_required" : la base n'est pas utilisable.
 *  - $reason "no_env" : pas de fichier .env, l'assistant recueille les identifiants et l'écrit ;
 *  - $reason "unknown_database" : .env présent mais la base n'existe pas, on la crée avec ses identifiants.
 * Ces routes ne sont jamais chargées quand un .env existe et que la connexion fonctionne.
 */
return static function (RouteCollectorProxyInterface $app, string $reason, Config $config, DatabaseSetup $setup): void {
    $json = static function (ResponseInterface $response, array $payload): ResponseInterface {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    };

    $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($json, $reason, $config): ResponseInterface {
        $payload = ['status' => 'config_required', 'reason' => $reason, 'target_version' => APP_VERSION];

        if ($reason === 'unknown_database') {
            $payload['database'] = $config->dbDatabase;
        }

        return $json($response, $payload);
    });

    $app->post('/install/database', function (ServerRequestInterface $request, ResponseInterface $response) use ($json, $reason, $config, $setup): ResponseInterface {
        if ($reason === 'unknown_database') {
            $credentials = [
                'host' => $config->dbHost,
                'port' => $config->dbPort,
                'database' => $config->dbDatabase,
                'username' => $config->dbUsername,
                'password' => $config->dbPassword,
            ];
        } else {
            $data = (array) $request->getParsedBody();
            $credentials = [
                'host' => trim((string) ($data['host'] ?? '')),
                'port' => (int) ($data['port'] ?? 3306),
                'database' => trim((string) ($data['database'] ?? '')),
                'username' => (string) ($data['username'] ?? ''),
                'password' => (string) ($data['password'] ?? ''),
            ];

            if ($credentials['host'] === '' || $credentials['database'] === '' || $credentials['username'] === '') {
                throw new HttpException($request, 'L\'hôte, le nom de la base et l\'utilisateur sont obligatoires.', 422);
            }

            if ($credentials['port'] < 1 || $credentials['port'] > 65535) {
                throw new HttpException($request, 'Port invalide.', 422);
            }

            if (preg_match('/[\r\n\0]/', implode('', $credentials)) === 1) {
                throw new HttpException($request, 'Caractères invalides dans les identifiants.', 422);
            }
        }

        try {
            $created = $setup->prepareDatabase($credentials);

            if ($reason === 'no_env') {
                $setup->writeEnv($credentials);
            }
        } catch (RuntimeException $exception) {
            throw new HttpException($request, $exception->getMessage(), 422, $exception);
        }

        return $json($response, ['status' => 'configured', 'created' => $created]);
    });
};
