<?php

declare(strict_types=1);

namespace Yawasla\Routes;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Http\Json;

/** Base injoignable (hors cas de l'assistant) : toute l'API répond 503, le détail est dans les logs. */
final class UnavailableRoutes implements RouteProvider
{
    public function register(RouteCollectorProxyInterface $api): void
    {
        $api->any('[/{path:.*}]', [$this, 'unavailable']);
    }

    public function unavailable(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Json::respond($response, ['error' => ['message' => 'Service temporairement indisponible.']], 503);
    }
}
