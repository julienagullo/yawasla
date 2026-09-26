<?php

declare(strict_types=1);

namespace Yawasla\Routes;

use Slim\Interfaces\RouteCollectorProxyInterface;

/**
 * Routes d'API d'un état de l'application (voir AppState::routes()), enregistrées sous APP_API_PREFIX.
 * Dépendances injectées par le conteneur. Les actions sont des méthodes ([$this, 'action']) et non des
 * closures : Slim lie les closures au conteneur, $this n'y désignerait plus la classe.
 */
interface RouteProvider
{
    public function register(RouteCollectorProxyInterface $api): void;
}
