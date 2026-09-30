<?php

declare(strict_types=1);

namespace Yawasla\Routes;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Core\Updater;
use Yawasla\Http\Auth;
use Yawasla\Http\Json;

/**
 * Nouvelles versions publiées (voir Updater), réservé à l'administration. Enregistrées avec les routes
 * de l'application (AppRoutes) : disponibles aussi pendant une mise à jour en attente.
 */
final class ReleaseRoutes implements RouteProvider
{
    public function __construct(private Auth $auth, private Updater $updater)
    {
    }

    public function register(RouteCollectorProxyInterface $api): void
    {
        $api->get('/admin/release', [$this, 'check']);
        $api->post('/admin/release/install', [$this, 'install']);
    }

    /** `release` à null : à jour, vérification désactivée ou serveur injoignable. */
    public function check(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->auth->requireAdmin();

        return Json::respond($response, [
            'current_version' => APP_VERSION,
            'release' => $this->updater->available(),
        ]);
    }

    /**
     * Remplace les fichiers de l'application. Le front recharge ensuite la page : s'il y a des migrations,
     * l'état "update_required" prend le relais.
     */
    public function install(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->auth->requireAdmin();

        return Json::respond($response, ['status' => 'ok', 'version' => $this->updater->install()]);
    }
}
