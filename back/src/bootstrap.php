<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Medoo\Medoo;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Throwable;
use Yawasla\Core\Config;
use Yawasla\Core\Container;
use Yawasla\Core\JsonErrorHandler;
use Yawasla\Core\Migrator;
use Yawasla\Core\Model;

if (!defined('APP_VERSION')) {
    throw new RuntimeException('La constante APP_VERSION doit être définie avant de charger le bootstrap.');
}

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$config = new Config($_ENV);

$varDir = __DIR__ . '/../var/' . $config->appEnv;
$logsDir = $varDir . '/logs';
$cacheDir = $varDir . '/cache';
$dbDir = $varDir . '/db';

if (!is_dir($logsDir) && !mkdir($logsDir, 0775, true) && !is_dir($logsDir)) {
    throw new RuntimeException("Impossible de créer le dossier de logs \"{$logsDir}\".");
}

$logger = new Logger('yawasla');
$logger->pushHandler(new StreamHandler($logsDir . '/app.log', Level::Debug));

if ($config->dbConnection === 'sqlite') {
    if (!is_dir($dbDir) && !mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
        throw new RuntimeException("Impossible de créer le dossier de base de données \"{$dbDir}\".");
    }

    $dbOptions = [
        'type' => 'sqlite',
        'database' => $dbDir . '/' . $config->dbDatabase,
    ];
} else {
    $dbOptions = [
        'type' => $config->dbConnection,
        'host' => $config->dbHost,
        'port' => $config->dbPort,
        'database' => $config->dbDatabase,
        'username' => $config->dbUsername,
        'password' => $config->dbPassword,
        'charset' => $config->dbCharset,
    ];
}

try {
    $database = new Medoo($dbOptions);
} catch (Throwable $exception) {
    $logger->error('Connexion à la base de données impossible.', ['exception' => $exception]);

    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'Service temporairement indisponible.']]);
    exit;
}

if ($config->dbConnection === 'sqlite') {
    // Contrairement à MySQL, sqlite n'applique pas les contraintes FOREIGN KEY par défaut :
    // sans ce pragma, elles existent dans le schéma mais ne sont jamais vérifiées.
    $database->pdo->exec('PRAGMA foreign_keys = ON');
}

Model::setConnection($database);

$migrator = new Migrator($database, __DIR__ . '/../database/migrations');

$container = new Container();
$container->set(Config::class, $config);
$container->set(LoggerInterface::class, $logger);
$container->set(Medoo::class, $database);
$container->set(Migrator::class, $migrator);
$container->set(ResponseFactoryInterface::class, new ResponseFactory());

$app = AppFactory::createFromContainer($container);

$dbVersion = $migrator->currentVersion();

// Le cache de routes n'est activé que pour l'état "normal" (dbVersion === APP_VERSION) :
// tant que install/update sont possibles, l'ensemble de routes chargé change selon l'état
// de la DB, et un cache figé sur le mauvais ensemble de routes resterait servi indéfiniment.
if (!$config->isDev() && $dbVersion === APP_VERSION) {
    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
        throw new RuntimeException("Impossible de créer le dossier de cache \"{$cacheDir}\".");
    }

    $app->getRouteCollector()->setCacheFile($cacheDir . '/routes.cache.php');
}

$errorMiddleware = $app->addErrorMiddleware(false, true, false);
$errorMiddleware->setDefaultErrorHandler($container->get(JsonErrorHandler::class));

if ($dbVersion === '0.0.0') {
    (require __DIR__ . '/install-routes.php')($app, $migrator);
} elseif (version_compare($dbVersion, APP_VERSION, '<')) {
    (require __DIR__ . '/update-routes.php')($app, $migrator);
} else {
    (require __DIR__ . '/routes.php')($app);
}

return $app;
