<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app): void {
    $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $response->getBody()->write((string) json_encode(['status' => 'ok']));

        return $response->withHeader('Content-Type', 'application/json');
    });
};
