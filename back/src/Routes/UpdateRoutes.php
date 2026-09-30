<?php

declare(strict_types=1);

namespace Yawasla\Routes;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Core\AppState;
use Yawasla\Core\Migrator;
use Yawasla\Http\Auth;
use Yawasla\Http\Json;

/**
 * État "update_required" : migrations en attente. Le site reste en ligne (routes de l'application),
 * seule l'administration affiche la mise à jour, qu'un administrateur connecté lance (migrate).
 */
final class UpdateRoutes implements RouteProvider
{
    public function __construct(private AppRoutes $app, private Migrator $migrator, private Auth $auth)
    {
    }

    public function register(RouteCollectorProxyInterface $api): void
    {
        $api->get('/', [$this, 'status']);
        $api->post('/admin/update/migrate', [$this, 'migrate']);
        $this->app->registerContent($api);
    }

    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Json::respond($response, [
            'status' => AppState::UPDATE_REQUIRED,
            'current_version' => $this->migrator->currentVersion(),
            'target_version' => APP_VERSION,
        ]);
    }

    public function migrate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->auth->requireAdmin();
        $this->migrator->migrateTo(APP_VERSION);

        return Json::respond($response, ['status' => 'ok']);
    }
}
