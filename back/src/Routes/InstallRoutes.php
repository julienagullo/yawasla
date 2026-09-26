<?php

declare(strict_types=1);

namespace Yawasla\Routes;

use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Core\ApiException;
use Yawasla\Core\AppState;
use Yawasla\Core\Config;
use Yawasla\Core\DatabaseSetup;
use Yawasla\Core\Migrator;
use Yawasla\Entity\Organization;
use Yawasla\Entity\User;
use Yawasla\Http\Json;

/**
 * État "install_required". L'installation se fait en 3 étapes, chacune n'exposant que sa propre route
 * (les autres répondent 404) :
 *  - "migrations"   : POST /install/migrate       (tables pas encore créées)
 *  - "organization" : POST /install/organization  (tables à jour, aucun organisme)
 *  - "user"         : POST /install/user          (organisme créé, aucun utilisateur)
 */
final class InstallRoutes implements RouteProvider
{
    public function __construct(
        private AppState $state,
        private Migrator $migrator,
        private Medoo $database,
        private LoggerInterface $logger,
        private Config $config,
    ) {
    }

    public function register(RouteCollectorProxyInterface $api): void
    {
        $api->get('/', [$this, 'status']);

        match ($this->state->step) {
            'migrations' => $api->post('/install/migrate', [$this, 'migrate']),
            'organization' => $api->post('/install/organization', [$this, 'createOrganization']),
            'user' => $api->post('/install/user', [$this, 'createUser']),
        };
    }

    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [
            'status' => AppState::INSTALL_REQUIRED,
            'step' => $this->state->step,
            'target_version' => APP_VERSION,
            'database' => $this->config->dbDatabase,
        ];

        if ($this->state->step === 'migrations') {
            $payload['migrations'] = $this->migrator->pendingVersions(APP_VERSION);
        }

        return Json::respond($response, $payload);
    }

    public function migrate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!DatabaseSetup::alignCharset($this->database)) {
            $this->logger->warning('Jeu de caractères de la base non aligné sur utf8mb4 (droits insuffisants) : les tables restent en utf8mb4 via leur propre collation.');
        }

        $this->migrator->migrateTo(APP_VERSION);

        return Json::respond($response, ['status' => 'migrated']);
    }

    public function createOrganization(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = (array) $request->getParsedBody();

        $email = $this->text($data, 'email', 'E-mail', 255, false);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException('validation.invalid_email', 'E-mail : adresse invalide.');
        }

        $domain = $this->text($data, 'domain', 'Domaine', 255, false);
        if ($domain !== null) {
            $domain = strtolower($domain);

            if (preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?(:\d{1,5})?$/', $domain) !== 1) {
                throw new ApiException('validation.invalid_domain', 'Domaine : format invalide (ex. mosquee.example.org).');
            }
        }

        $organization = (new Organization())->fill([
            'name' => $this->text($data, 'name', 'Nom de l\'organisme', 255, true),
            'address' => $this->text($data, 'address', 'Adresse', 255, false),
            'city' => $this->text($data, 'city', 'Ville', 255, false),
            'postal_code' => $this->text($data, 'postal_code', 'Code postal', 20, false),
            'phone' => $this->text($data, 'phone', 'Téléphone', 20, false),
            'email' => $email,
            'domain' => $domain,
        ]);

        if (Organization::count() > 0 || !$organization->save()) {
            throw new ApiException('install.organization_failed', 'Création de l\'organisme impossible.', [], 409);
        }

        return Json::respond($response, ['status' => 'organization_created']);
    }

    public function createUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = (array) $request->getParsedBody();

        $email = $this->text($data, 'email', 'E-mail', 255, true);
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
            'first_name' => $this->text($data, 'first_name', 'Prénom', 100, true),
            'last_name' => $this->text($data, 'last_name', 'Nom', 100, true),
            'display_name' => $this->text($data, 'display_name', 'Nom d\'affichage', 100, true),
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => User::ROLE_OWNER,
        ]);

        if (!$owner->save()) {
            throw new ApiException('install.user_failed', 'Création du compte impossible.', [], 500);
        }

        return Json::respond($response, ['status' => 'installed']);
    }

    /**
     * Valeur texte : trim, longueur max, null si vide et facultative. Le front traduit `field` (nom du champ API).
     *
     * @param array<string, mixed> $data
     */
    private function text(array $data, string $key, string $label, int $max, bool $required): ?string
    {
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
    }
}
