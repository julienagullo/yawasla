<?php

declare(strict_types=1);

namespace Yawasla\Http;

use Yawasla\Core\Config;
use Yawasla\Core\Paths;
use Yawasla\Entity\User;

/**
 * Utilisateur connecté, mémorisé en session PHP pour 24 h maximum.
 * Pour l'instant, seuls les rôles owner et admin peuvent se connecter : l'administration est le seul
 * espace privé.
 */
final class Auth
{
    public const ADMIN_ROLES = [User::ROLE_OWNER, User::ROLE_ADMIN];

    private const COOKIE = 'yawasla_session';
    private const KEY = 'user_id';
    private const LOGGED_AT = 'logged_at';
    /** Durée absolue d'une session, activité ou non */
    private const LIFETIME = 86400;

    public function __construct(private Urls $urls, private Config $config, private Paths $paths)
    {
    }

    public static function canAccessAdmin(User $user): bool
    {
        return in_array($user->role, self::ADMIN_ROLES, true);
    }

    public function user(): ?User
    {
        // Visiteur sans cookie : inutile d'ouvrir (et de créer) une session
        if (!isset($_COOKIE[self::COOKIE])) {
            return null;
        }

        $this->start();
        $id = $_SESSION[self::KEY] ?? null;
        $expired = time() - (int) ($_SESSION[self::LOGGED_AT] ?? 0) > self::LIFETIME;
        $user = is_int($id) && !$expired ? User::find($id) : null;

        // Session expirée, compte supprimé ou rôle retiré depuis la connexion
        if ($user === null || !self::canAccessAdmin($user)) {
            $this->logout();

            return null;
        }

        return $user;
    }

    public function login(User $user): void
    {
        $this->start();
        // Nouvel identifiant de session à la connexion (fixation de session)
        session_regenerate_id(true);
        $_SESSION[self::KEY] = (int) $user->id;
        $_SESSION[self::LOGGED_AT] = time();
    }

    public function logout(): void
    {
        if (!isset($_COOKIE[self::COOKIE])) {
            return;
        }

        $this->start();
        $_SESSION = [];
        session_destroy();
        setcookie(self::COOKIE, '', ['expires' => 1] + $this->cookieParams());
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(self::COOKIE);
        session_set_cookie_params(['lifetime' => self::LIFETIME] + $this->cookieParams());
        // Dossier propre à l'app : le stockage par défaut peut être partagé (mutualisé) ou purgé par un
        // cron qui ignore notre durée (Debian : 24 min). Debian désactive aussi le ramasse-miettes de
        // PHP (gc_probability = 0) : réactivé, sinon les sessions expirées ne seraient jamais supprimées
        session_save_path($this->paths->sessionsDir());
        session_start([
            'use_strict_mode' => true,
            'gc_maxlifetime' => self::LIFETIME,
            'gc_probability' => 1,
            'gc_divisor' => 100,
        ]);
    }

    /** @return array{path: string, secure: bool, httponly: bool, samesite: string} */
    private function cookieParams(): array
    {
        return [
            'path' => $this->urls->basePath . '/',
            // Toujours, hors dev : derrière un proxy ou un CDN, PHP peut voir du HTTP alors que le site est en HTTPS
            'secure' => !$this->config->isDev(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
