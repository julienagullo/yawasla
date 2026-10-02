<?php

declare(strict_types=1);

namespace Yawasla\Routes;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteCollectorProxyInterface;
use Yawasla\Core\ApiException;
use Yawasla\Core\Config;
use Yawasla\Core\Paths;
use Yawasla\Core\Updater;
use Yawasla\Entity\User;
use Yawasla\Http\Auth;
use Yawasla\Http\Json;
use Yawasla\Http\LoginThrottle;
use Yawasla\Http\PasswordResetStore;
use Yawasla\Http\Urls;
use Yawasla\Mail\Mailer;
use Yawasla\Mail\MailTemplate;

/** Connexion à l'administration, enregistrée avec les routes de l'application (AppRoutes). */
final class AuthRoutes implements RouteProvider
{
    public function __construct(
        private Auth $auth,
        private LoginThrottle $throttle,
        private Paths $paths,
        private PasswordResetStore $resets,
        private Mailer $mailer,
        private MailTemplate $templates,
        private Urls $urls,
        private Config $config,
        private Updater $updater,
    ) {
    }

    public function register(RouteCollectorProxyInterface $api): void
    {
        $api->get('/auth/me', [$this, 'me']);
        $api->post('/auth/login', [$this, 'login']);
        $api->post('/auth/logout', [$this, 'logout']);
        $api->post('/auth/forgot', [$this, 'forgot']);
        $api->post('/auth/reset', [$this, 'reset']);
    }

    /** Visiteur non connecté : `user` à null plutôt qu'une 401, c'est un cas normal pour le front. */
    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->user();

        return Json::respond($response, ['user' => $user !== null ? $this->present($user) : null]);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new ApiException('auth.missing_credentials', 'L’e-mail et le mot de passe sont obligatoires.');
        }

        // REMOTE_ADDR et non X-Forwarded-For, que le client peut falsifier
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
        $status = $this->throttle->status($ip);

        // Au-delà du seuil : la requête n'atteint jamais la base ni bcrypt. On renvoie exactement la
        // même erreur qu'un mot de passe faux — l'attaquant ne sait pas qu'il est repéré, et un bon
        // mot de passe ne lui est jamais confirmé. Voir LoginThrottle pour les paliers.
        if ($status !== LoginThrottle::OK) {
            $now = $this->throttle->fail($ip);

            // Franchissement du seuil de blocage (tarpit → blocked) : une seule alerte au propriétaire
            if ($status === LoginThrottle::TARPIT && $now === LoginThrottle::BLOCKED) {
                $this->alertOwner($request, $ip);
            }

            // Tarpit (10-99) : pause avant la réponse, connexions enlisées. Blocage (>= 100) : réponse
            // immédiate, on cesse d'immobiliser un worker par requête (protège le pool sous flood)
            if ($status === LoginThrottle::TARPIT) {
                usleep(2_000_000 + random_int(0, 800_000));
            }
            throw new ApiException('auth.invalid_credentials', 'E-mail ou mot de passe incorrect.', [], 401);
        }

        $user = User::first(['email' => $email]);

        $hash = $user !== null ? $user->password : $this->dummyHash();
        if (!password_verify($password, $hash) || $user === null) {
            $this->throttle->fail($ip);
            throw new ApiException('auth.invalid_credentials', 'E-mail ou mot de passe incorrect.', [], 401);
        }

        if (!Auth::canAccessAdmin($user)) {
            throw new ApiException('auth.forbidden', 'Ce compte n’a pas accès à l’administration.', [], 403);
        }

        $this->throttle->clear($ip);
        $this->auth->login($user);
        // Nouvelle version disponible : affichée ensuite dans l'administration (ReleaseRoutes)
        $this->updater->check();

        return Json::respond($response, ['user' => $this->present($user)]);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->auth->logout();

        return Json::respond($response, ['status' => 'ok']);
    }

    /**
     * Demande de réinitialisation. Réponse toujours identique (pas d'énumération) : le mail n'est
     * envoyé que si l'adresse correspond à un compte pouvant accéder à l'administration.
     */
    public function forgot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $start = microtime(true);
        $data = (array) $request->getParsedBody();
        $email = trim((string) ($data['email'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $user = User::first(['email' => $email]);

            // Pas de nouveau mail tant qu'un jeton est actif (anti-bombing : 1 mail toutes les 15 min max)
            if ($user !== null && Auth::canAccessAdmin($user) && !$this->resets->hasActiveFor((int) $user->id)) {
                $token = $this->resets->issue((int) $user->id);
                $base = $this->baseUrl($request);
                $mail = $this->templates->create('password_reset', $user->locale, [
                    'name' => $user->display_name,
                    'site' => $this->siteName($base),
                    'link' => $base . '/admin/reset?token=' . $token,
                ]);
                $this->mailer->send($email, $mail['subject'], $mail['text'], $mail['html']);
            }
        }

        // Durée portée à ~0,5 s quel que soit le chemin (envoi ou non) : l'écart compte existant /
        // inexistant devient indétectable, sans jamais ajouter un délai par-dessus un envoi déjà lent
        $remaining = 0.5 - (microtime(true) - $start);
        if ($remaining > 0) {
            usleep((int) ($remaining * 1_000_000));
        }

        return Json::respond($response, ['status' => 'ok']);
    }

    public function reset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = (array) $request->getParsedBody();
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if (mb_strlen($password) < 8) {
            throw new ApiException('validation.password_too_short', 'Le mot de passe doit contenir au moins 8 caractères.', ['min' => 8]);
        }
        if (strlen($password) > 72) {
            throw new ApiException('validation.password_too_long', 'Le mot de passe ne doit pas dépasser 72 octets.', ['max' => 72]);
        }

        $userId = $token !== '' ? $this->resets->consume($token) : null;
        $user = $userId !== null ? User::find($userId) : null;

        if ($user === null) {
            throw new ApiException('auth.invalid_reset_token', 'Lien de réinitialisation invalide ou expiré.', [], 400);
        }

        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->save();

        return Json::respond($response, ['status' => 'ok']);
    }

    /** Alerte le propriétaire quand une IP atteint le seuil de blocage (attaque en cours). */
    private function alertOwner(ServerRequestInterface $request, string $ip): void
    {
        $owner = User::first(['role' => User::ROLE_OWNER]);
        if ($owner === null) {
            return;
        }

        $mail = $this->templates->create('login_alert', $owner->locale, [
            'name' => $owner->display_name,
            'site' => $this->siteName($this->baseUrl($request)),
            'datetime' => $this->formatDate($owner->locale),
            'ip' => $ip,
        ]);
        $this->mailer->send($owner->email, $mail['subject'], $mail['text'], $mail['html']);
    }

    /** Base publique des liens : APP_URL si défini (fiable derrière un proxy), sinon la requête. */
    private function baseUrl(ServerRequestInterface $request): string
    {
        return $this->config->appUrl !== ''
            ? $this->config->appUrl
            : rtrim((string) $request->getUri()->withPath('')->withQuery(''), '/') . $this->urls->basePath;
    }

    /** Nom du site affiché dans les mails : l'hôte de la base publique (jamais l'organisme). */
    private function siteName(string $base): string
    {
        return (string) (parse_url($base, PHP_URL_HOST) ?: $base);
    }

    /** Date/heure courante : jour/mois en fr (01/10/26 12:30), mois/jour partout ailleurs (10/01/26 12:30). */
    private function formatDate(string $locale): string
    {
        $now = new DateTimeImmutable();

        return $now->format($locale === 'fr' ? 'd/m/y H:i' : 'm/d/y H:i');
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    private function dummyHash(): string
    {
        $file = $this->paths->cacheDir() . '/dummy_hash';
        $hash = @file_get_contents($file);

        if (!is_string($hash) || !str_starts_with($hash, '$2y$')) {
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $tmp = $file . '.' . getmypid();
            file_put_contents($tmp, $hash);
            rename($tmp, $file);
        }

        return $hash;
    }
}
