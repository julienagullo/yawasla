<?php

declare(strict_types=1);

use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Core\Config;
use Yawasla\Core\DatabaseSetup;
use Yawasla\Core\Migrator;
use Yawasla\Entity\Organization;
use Yawasla\Entity\User;

/**
 * Routes de l'état "install_required". L'installation se fait en 3 étapes, chacune n'exposant
 * que sa propre route (les autres répondent 404) :
 *  - "migrations"   : POST /install/migrate       (tables pas encore créées)
 *  - "organization" : POST /install/organization  (tables à jour, aucun organisme)
 *  - "user"         : POST /install/user          (organisme créé, aucun utilisateur)
 * L'étape en cours est déterminée par le bootstrap et renvoyée dans GET /.
 */
return static function (
    RouteCollectorProxyInterface $app,
    Migrator $migrator,
    Medoo $database,
    LoggerInterface $logger,
    Config $config,
    string $step,
): void {
    $json = static function (ResponseInterface $response, array $payload): ResponseInterface {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    };

    /** Valeur texte : trim, longueur max, null si vide et facultative. */
    $text = static function (ServerRequestInterface $request, array $data, string $key, string $label, int $max, bool $required): ?string {
        $value = trim((string) ($data[$key] ?? ''));

        if ($value === '') {
            if ($required) {
                throw new HttpException($request, "{$label} : champ obligatoire.", 422);
            }

            return null;
        }

        if (mb_strlen($value) > $max) {
            throw new HttpException($request, "{$label} : {$max} caractères maximum.", 422);
        }

        return $value;
    };

    $app->get('/', function (ServerRequestInterface $request, ResponseInterface $response) use ($json, $migrator, $config, $step): ResponseInterface {
        $payload = [
            'status' => 'install_required',
            'step' => $step,
            'target_version' => APP_VERSION,
            'database' => $config->dbDatabase,
        ];

        if ($step === 'migrations') {
            $payload['migrations'] = $migrator->pendingVersions(APP_VERSION);
        }

        return $json($response, $payload);
    });

    if ($step === 'migrations') {
        $app->post('/install/migrate', function (ServerRequestInterface $request, ResponseInterface $response) use ($json, $migrator, $database, $logger): ResponseInterface {
            if (!DatabaseSetup::alignCharset($database)) {
                $logger->warning('Jeu de caractères de la base non aligné sur utf8mb4 (droits insuffisants) : les tables restent en utf8mb4 via leur propre collation.');
            }

            $migrator->migrateTo(APP_VERSION);

            return $json($response, ['status' => 'migrated']);
        });
    }

    if ($step === 'organization') {
        $app->post('/install/organization', function (ServerRequestInterface $request, ResponseInterface $response) use ($json, $text): ResponseInterface {
            $data = (array) $request->getParsedBody();

            $email = $text($request, $data, 'email', 'E-mail', 255, false);
            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new HttpException($request, 'E-mail : adresse invalide.', 422);
            }

            $domain = $text($request, $data, 'domain', 'Domaine', 255, false);
            if ($domain !== null) {
                $domain = strtolower($domain);

                if (preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?$/', $domain) !== 1) {
                    throw new HttpException($request, 'Domaine : format invalide (ex. mosquee.example.org).', 422);
                }
            }

            $organization = (new Organization())->fill([
                'name' => $text($request, $data, 'name', 'Nom de l\'organisme', 255, true),
                'address' => $text($request, $data, 'address', 'Adresse', 255, false),
                'city' => $text($request, $data, 'city', 'Ville', 255, false),
                'postal_code' => $text($request, $data, 'postal_code', 'Code postal', 20, false),
                'phone' => $text($request, $data, 'phone', 'Téléphone', 20, false),
                'email' => $email,
                'domain' => $domain,
            ]);

            if (Organization::count() > 0 || !$organization->save()) {
                throw new HttpException($request, 'Création de l\'organisme impossible.', 409);
            }

            return $json($response, ['status' => 'organization_created']);
        });
    }

    if ($step === 'user') {
        $app->post('/install/user', function (ServerRequestInterface $request, ResponseInterface $response) use ($json, $text): ResponseInterface {
            $data = (array) $request->getParsedBody();

            $email = $text($request, $data, 'email', 'E-mail', 255, true);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new HttpException($request, 'E-mail : adresse invalide.', 422);
            }

            $password = (string) ($data['password'] ?? '');
            if (mb_strlen($password) < 8) {
                throw new HttpException($request, 'Le mot de passe doit contenir au moins 8 caractères.', 422);
            }
            // bcrypt ignore silencieusement tout ce qui dépasse 72 octets
            if (strlen($password) > 72) {
                throw new HttpException($request, 'Le mot de passe ne doit pas dépasser 72 octets.', 422);
            }

            $organization = Organization::first(['ORDER' => ['id' => 'ASC']]);

            if ($organization === null || User::count() > 0) {
                throw new HttpException($request, 'Création du compte impossible.', 409);
            }

            $owner = (new User())->fill([
                'organization_id' => $organization->id,
                'first_name' => $text($request, $data, 'first_name', 'Prénom', 100, true),
                'last_name' => $text($request, $data, 'last_name', 'Nom', 100, true),
                'display_name' => $text($request, $data, 'display_name', 'Nom d\'affichage', 100, true),
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => User::ROLE_OWNER,
            ]);

            if (!$owner->save()) {
                throw new HttpException($request, 'Création du compte impossible.', 500);
            }

            return $json($response, ['status' => 'installed']);
        });
    }
};
