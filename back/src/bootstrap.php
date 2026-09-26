<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Medoo\Medoo;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Yawasla\Core\AppShell;
use Yawasla\Core\AppState;
use Yawasla\Core\Config;
use Yawasla\Core\Container;
use Yawasla\Core\DatabaseSetup;
use Yawasla\Core\JsonErrorHandler;
use Yawasla\Core\Migrator;
use Yawasla\Core\Paths;
use Yawasla\Http\ShellAction;
use Yawasla\Http\Urls;

if (!defined('APP_VERSION')) {
    throw new RuntimeException('La constante APP_VERSION doit être définie avant de charger le bootstrap.');
}

if (!defined('APP_API_PREFIX')) {
    throw new RuntimeException('La constante APP_API_PREFIX doit être définie avant de charger le bootstrap.');
}

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

// .env chargé en dev comme en prod : aucune dépendance aux variables d'environnement du serveur
Dotenv::createImmutable($root)->safeLoad();
$config = new Config($_ENV);
$paths = new Paths($root, $config->appEnv);

$logger = new Logger('yawasla');
$logger->pushHandler(new StreamHandler($paths->logsDir() . '/app.log', Logger::DEBUG));

// Connexion (fail-fast) et version de la base → état de l'application et routes à charger
$state = AppState::resolve($config, $paths, $logger);

$container = new Container();
$container->set(Config::class, $config);
$container->set(Paths::class, $paths);
$container->set(Urls::class, $urls = Urls::fromServer($_SERVER, APP_API_PREFIX));
$container->set(AppState::class, $state);
$container->set(LoggerInterface::class, $logger);
$container->set(ResponseFactoryInterface::class, new ResponseFactory());
$container->set(DatabaseSetup::class, new DatabaseSetup($paths->envFile()));
$container->set(AppShell::class, new AppShell($paths->shellTemplate()));

if ($state->database !== null) {
    $container->set(Medoo::class, $state->database);
    $container->set(Migrator::class, $state->migrator);
}

$app = AppFactory::createFromContainer($container);
$container->set(App::class, $app);
$app->setBasePath($urls->basePath);

if (!$config->isDev() && $state->allowsRouteCache()) {
    $app->getRouteCollector()->setCacheFile($paths->cacheDir() . '/routes.cache.php');
}

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(false, true, false)->setDefaultErrorHandler($container->get(JsonErrorHandler::class));

// API : routes de l'état courant, toutes préfixées par APP_API_PREFIX
$routes = $container->get($state->routes());
$app->group(APP_API_PREFIX, function (RouteCollectorProxyInterface $api) use ($routes): void {
    $routes->register($api);
});

// Toute autre URL (GET) : page du front. Le motif exclut le préfixe d'API pour qu'une route d'API
// inconnue réponde 404 en JSON, quelle que soit sa méthode
$apiSegment = preg_quote(ltrim(APP_API_PREFIX, '/'), '~');
$app->get('/{path:(?!' . $apiSegment . '(?:/|$)).*}', ShellAction::class);

return $app;
