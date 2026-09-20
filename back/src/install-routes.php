<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Yawasla\Core\Migrator;

return static function (App $app, Migrator $migrator): void {
    $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($migrator): ResponseInterface {
        $response->getBody()->write((string) json_encode([
            'status' => 'install_required',
            'target_version' => APP_VERSION,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/install', function (ServerRequestInterface $request, ResponseInterface $response) use ($migrator): ResponseInterface {
        // TODO : recueillir les infos de l'organization + créer le premier compte admin
        // à partir des entités Organization/User — pour l'instant, seules les
        // migrations sont exécutées.
        $migrator->migrateTo(APP_VERSION);

        $response->getBody()->write((string) json_encode(['status' => 'installed']));

        return $response->withHeader('Content-Type', 'application/json');
    });
};
