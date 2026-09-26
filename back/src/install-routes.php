<?php

declare(strict_types=1);

use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Core\ApiException;
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

    /** Valeur texte : trim, longueur max, null si vide et facultative. Le front traduit `field` (nom du champ API). */
    $text = static function (array $data, string $key, string $label, int $max, bool $required): ?string {
        $value = trim((string) ($data[$key] ?? ''));

        if ($value === '') {
            if ($required) {
                throw new ApiException('validation.required', "{$label} : champ obligatoire.", ['field' => $key]);
            }

            return null;
        }

        if (mb_strlen($value) > $max) {
            throw new ApiException('validation.too_long', "{$label} : {$max} caractères maximum.", ['field' => $key, 'max' => $max]);
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

            $email = $text($data, 'email', 'E-mail', 255, false);
            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new ApiException('validation.invalid_email', 'E-mail : adresse invalide.');
            }

            $domain = $text($data, 'domain', 'Domaine', 255, false);
            if ($domain !== null) {
                $domain = strtolower($domain);

                if (preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?$/', $domain) !== 1) {
                    throw new ApiException('validation.invalid_domain', 'Domaine : format invalide (ex. mosquee.example.org).');
                }
            }

            $organization = (new Organization())->fill([
                'name' => $text($data, 'name', 'Nom de l\'organisme', 255, true),
                'address' => $text($data, 'address', 'Adresse', 255, false),
                'city' => $text($data, 'city', 'Ville', 255, false),
                'postal_code' => $text($data, 'postal_code', 'Code postal', 20, false),
                'phone' => $text($data, 'phone', 'Téléphone', 20, false),
                'email' => $email,
                'domain' => $domain,
            ]);

            if (Organization::count() > 0 || !$organization->save()) {
                throw new ApiException('install.organization_failed', 'Création de l\'organisme impossible.', [], 409);
            }

            return $json($response, ['status' => 'organization_created']);
        });
    }

    if ($step === 'user') {
        $app->post('/install/user', function (ServerRequestInterface $request, ResponseInterface $response) use ($json, $text): ResponseInterface {
            $data = (array) $request->getParsedBody();

            $email = $text($data, 'email', 'E-mail', 255, true);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new ApiException('validation.invalid_email', 'E-mail : adresse invalide.');
            }

            $password = (string) ($data['password'] ?? '');
            if (mb_strlen($password) < 8) {
                throw new ApiException('validation.password_too_short', 'Le mot de passe doit contenir au moins 8 caractères.', ['min' => 8]);
            }
            // bcrypt ignore silencieusement tout ce qui dépasse 72 octets
            if (strlen($password) > 72) {
                throw new ApiException('validation.password_too_long', 'Le mot de passe ne doit pas dépasser 72 octets.', ['max' => 72]);
            }

            $organization = Organization::first(['ORDER' => ['id' => 'ASC']]);

            if ($organization === null || User::count() > 0) {
                throw new ApiException('install.user_failed', 'Création du compte impossible.', [], 409);
            }

            $owner = (new User())->fill([
                'organization_id' => $organization->id,
                'first_name' => $text($data, 'first_name', 'Prénom', 100, true),
                'last_name' => $text($data, 'last_name', 'Nom', 100, true),
                'display_name' => $text($data, 'display_name', 'Nom d\'affichage', 100, true),
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => User::ROLE_OWNER,
            ]);

            if (!$owner->save()) {
                throw new ApiException('install.user_failed', 'Création du compte impossible.', [], 500);
            }

            return $json($response, ['status' => 'installed']);
        });
    }
};
