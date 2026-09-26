<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Medoo\Medoo;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Throwable;
use Yawasla\Core\AppShell;
use Yawasla\Core\Config;
use Yawasla\Core\Container;
use Yawasla\Core\DatabaseSetup;
use Yawasla\Core\JsonErrorHandler;
use Yawasla\Core\Migrator;
use Yawasla\Core\Model;
use Yawasla\Entity\Organization;
use Yawasla\Entity\User;

if (!defined('APP_VERSION')) {
    throw new RuntimeException('La constante APP_VERSION doit être définie avant de charger le bootstrap.');
}

if (!defined('APP_API_PREFIX')) {
    throw new RuntimeException('La constante APP_API_PREFIX doit être définie avant de charger le bootstrap.');
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
$logger->pushHandler(new StreamHandler($logsDir . '/app.log', Logger::DEBUG));

$setup = new DatabaseSetup(__DIR__ . '/../.env');

// Chemin de base déduit de l'emplacement de index.php : vide à la racine d'un domaine,
// "/yawasla" si l'app est servie depuis un sous-dossier ou un alias Apache.
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$apiBase = $basePath . APP_API_PREFIX;

// index.php est l'unique point d'entrée : /api/* pour l'API, tout le reste sert le front (AppShell)
$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$isApiRequest = $requestPath === $apiBase || str_starts_with($requestPath, $apiBase . '/');

$shell = new AppShell(__DIR__ . '/../resources/app.html');

// Si la base n'est pas utilisable, l'assistant d'installation prend la main (état "config_required") :
//  - "no_env" : pas de fichier .env, il faut d'abord renseigner la connexion à la base ;
//  - "unknown_database" : le serveur répond mais la base n'existe pas (créée par l'assistant).
// Toute autre erreur de connexion reste un 503 : ouvrir l'assistant sur un site déjà installé
// lors d'une simple panne MySQL permettrait à n'importe qui de réécrire la configuration.
$database = null;
$setupReason = null;

if (!$setup->hasEnvFile()) {
    $setupReason = 'no_env';
} else {
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

    $hosts = $config->dbConnection === 'mysql' ? DatabaseSetup::hostCandidates($config->dbHost) : [null];

    foreach ($hosts as $host) {
        if ($host !== null) {
            $dbOptions['host'] = $host;
        }

        try {
            $database = new Medoo($dbOptions);
            break;
        } catch (Throwable $exception) {
            // "localhost" injoignable (socket Unix introuvable) : on retente en TCP
            if ($host !== end($hosts) && DatabaseSetup::isUnreachable($exception)) {
                continue;
            }

            if ($config->dbConnection === 'mysql' && DatabaseSetup::isUnknownDatabase($exception)) {
                $setupReason = 'unknown_database';
            } else {
                $logger->error('Connexion à la base de données impossible.', ['exception' => $exception]);

                http_response_code(503);

                // Page du front sans état injecté : il interroge l'API, reçoit ce 503 et affiche l'erreur
                if (!$isApiRequest && $shell->exists()) {
                    header('Content-Type: text/html; charset=utf-8');
                    echo $shell->render($basePath, ['basePath' => $basePath, 'apiBase' => $apiBase]);
                    exit;
                }

                header('Content-Type: application/json');
                echo json_encode(['error' => ['message' => 'Service temporairement indisponible.']]);
                exit;
            }

            break;
        }
    }
}

$container = new Container();
$container->set(Config::class, $config);
$container->set(LoggerInterface::class, $logger);
$container->set(ResponseFactoryInterface::class, new ResponseFactory());

$migrator = null;
$dbVersion = null;
$installStep = null;

if ($database !== null) {
    if ($config->dbConnection === 'sqlite') {
        // Contrairement à MySQL, sqlite n'applique pas les contraintes FOREIGN KEY par défaut :
        // sans ce pragma, elles existent dans le schéma mais ne sont jamais vérifiées.
        $database->pdo->exec('PRAGMA foreign_keys = ON');
    }

    Model::setConnection($database);

    $migrator = new Migrator($database, __DIR__ . '/../database/migrations');
    $dbVersion = $migrator->currentVersion();

    // Étape d'installation en cours (null : rien à installer). Les étapes se déduisent de la base,
    // ce qui permet de reprendre au bon endroit après une interruption :
    //  - migrations   : jamais installé ;
    //  - organization : tables à jour, ni utilisateur ni organisme ;
    //  - user         : organisme créé, pas encore d'utilisateur.
    // Limite assumée : supprimer à la main tous les utilisateurs d'un site n'ayant qu'un organisme
    // rouvre l'étape "user" (aucune fonctionnalité de l'app ne permet de le faire).
    $installStep = match (true) {
        $dbVersion === Migrator::NOT_INSTALLED => 'migrations',
        $dbVersion === APP_VERSION && User::count() === 0 => Organization::count() === 0 ? 'organization' : 'user',
        default => null,
    };

    $container->set(Medoo::class, $database);
    $container->set(Migrator::class, $migrator);
}

$app = AppFactory::createFromContainer($container);

$app->setBasePath($basePath);

// Le cache de routes n'est activé que pour l'état "normal" (dbVersion === APP_VERSION) :
// tant que install/update sont possibles, l'ensemble de routes chargé change selon l'état
// de la DB, et un cache figé sur le mauvais ensemble de routes resterait servi indéfiniment.
if (!$config->isDev() && $dbVersion === APP_VERSION && $installStep === null) {
    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
        throw new RuntimeException("Impossible de créer le dossier de cache \"{$cacheDir}\".");
    }

    $app->getRouteCollector()->setCacheFile($cacheDir . '/routes.cache.php');
}

$app->addBodyParsingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(false, true, false);
$errorMiddleware->setDefaultErrorHandler($container->get(JsonErrorHandler::class));

// Toutes les routes de l'API sont préfixées par APP_API_PREFIX (le front React est servi à la racine).
$app->group(APP_API_PREFIX, function (RouteCollectorProxyInterface $group) use ($dbVersion, $installStep, $migrator, $database, $logger, $setup, $setupReason, $config): void {
    if ($setupReason !== null) {
        (require __DIR__ . '/setup-routes.php')($group, $setupReason, $config, $setup);
    } elseif ($installStep !== null) {
        (require __DIR__ . '/install-routes.php')($group, $migrator, $database, $logger, $config, $installStep);
    } elseif (version_compare($dbVersion, APP_VERSION, '<')) {
        (require __DIR__ . '/update-routes.php')($group, $migrator);
    } else {
        (require __DIR__ . '/routes.php')($group);
    }
});

// Toute autre URL (GET) : page du front, avec l'état de l'app (GET /api/) déjà injecté,
// ce qui évite au front un aller-retour au démarrage
$app->get('/{path:.*}', function (ServerRequestInterface $request, ResponseInterface $response) use ($app, $shell, $basePath, $apiBase, $isApiRequest): ResponseInterface {
    // Route d'API inconnue : 404 JSON, jamais la page HTML
    if ($isApiRequest) {
        throw new HttpNotFoundException($request);
    }

    if (!$shell->exists()) {
        $response->getBody()->write('Front non compilé : en dev, utilisez le serveur Vite (npm run dev dans front/).');

        return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    $boot = ['basePath' => $basePath, 'apiBase' => $apiBase];

    // Sous-requête interne : même logique et même réponse que si le front appelait GET /api/.
    // Requête neuve : la requête courante porte déjà son résultat de routage (cette route), que Slim
    // réutiliserait → boucle infinie
    $statusRequest = (new ServerRequestFactory())->createServerRequest(
        'GET',
        $request->getUri()->withPath($apiBase . '/')->withQuery(''),
        $request->getServerParams(),
    );
    $statusResponse = $app->handle($statusRequest);
    if ($statusResponse->getStatusCode() === 200) {
        $boot['status'] = json_decode((string) $statusResponse->getBody(), true);
    }

    $response->getBody()->write($shell->render($basePath, $boot));

    return $response
        ->withHeader('Content-Type', 'text/html; charset=utf-8')
        // L'état injecté change avec la base : la page ne doit pas être mise en cache
        ->withHeader('Cache-Control', 'no-store');
});

return $app;
