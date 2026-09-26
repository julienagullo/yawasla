<?php

declare(strict_types=1);

namespace Yawasla\Routes;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Http\Json;

/** Fonctionnement normal : application installée et à jour. */
final class AppRoutes implements RouteProvider
{
    public function register(RouteCollectorProxyInterface $api): void
    {
        $api->get('/', [$this, 'status']);
        $this->registerContent($api);
    }

    /**
     * Routes de l'application (newsroom, administration…), aussi chargées pendant une mise à jour en
     * attente (UpdateRoutes) : le site reste en ligne tant que l'administrateur n'a pas migré.
     */
    public function registerContent(RouteCollectorProxyInterface $api): void
    {
    }

    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Json::respond($response, ['status' => 'ok']);
    }
}
