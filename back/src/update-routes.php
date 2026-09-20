<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Yawasla\Core\Migrator;

return static function (App $app, Migrator $migrator): void {
    $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($migrator): ResponseInterface {
        $response->getBody()->write((string) json_encode([
            'status' => 'update_required',
            'current_version' => $migrator->currentVersion(),
            'target_version' => APP_VERSION,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/update', function (ServerRequestInterface $request, ResponseInterface $response) use ($migrator): ResponseInterface {
        $migrator->migrateTo(APP_VERSION);

        $response->getBody()->write((string) json_encode(['status' => 'updated']));

        return $response->withHeader('Content-Type', 'application/json');
    });
};
