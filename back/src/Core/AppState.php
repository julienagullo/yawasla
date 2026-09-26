<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Medoo\Medoo;
use Psr\Log\LoggerInterface;
use Throwable;
use Yawasla\Entity\Organization;
use Yawasla\Entity\User;
use Yawasla\Routes\AppRoutes;
use Yawasla\Routes\InstallRoutes;
use Yawasla\Routes\SetupRoutes;
use Yawasla\Routes\UnavailableRoutes;
use Yawasla\Routes\UpdateRoutes;

/**
 * État de l'application, déduit du .env, de la connexion et de la version de la base. Chaque état
 * charge son propre ensemble de routes d'API (voir routes()).
 */
final class AppState
{
    /** Base inutilisable : pas de .env ($reason "no_env") ou base inexistante ("unknown_database") */
    public const CONFIG_REQUIRED = 'config_required';
    /** Installation en cours, $step déduit de la base (reprise possible après une interruption) */
    public const INSTALL_REQUIRED = 'install_required';
    /**
     * Migrations en attente. Le site public reste en ligne, seule l'administration affiche la mise à
     * jour. Une release sans migration ne passe jamais par cet état.
     */
    public const UPDATE_REQUIRED = 'update_required';
    public const READY = 'ready';
    /**
     * Toute autre erreur de connexion : 503. Ouvrir l'assistant lors d'une simple panne MySQL sur un
     * site installé permettrait à n'importe qui de réécrire la configuration.
     */
    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        public string $status,
        public ?string $reason = null,
        public ?string $step = null,
        public ?Medoo $database = null,
        public ?Migrator $migrator = null,
    ) {
    }

    public static function resolve(Config $config, Paths $paths, LoggerInterface $logger): self
    {
        if (!is_file($paths->envFile())) {
            return new self(self::CONFIG_REQUIRED, 'no_env');
        }

        try {
            $database = DatabaseConnector::connect($config, $paths);
        } catch (UnknownDatabaseException) {
            return new self(self::CONFIG_REQUIRED, 'unknown_database');
        } catch (Throwable $exception) {
            $logger->error('Connexion à la base de données impossible.', ['exception' => $exception]);

            return new self(self::UNAVAILABLE);
        }

        // Nécessaire dès ici : le calcul de l'étape d'installation interroge les entités
        Model::setConnection($database);

        $migrator = new Migrator($database, $paths->migrations());
        $dbVersion = $migrator->currentVersion();
        // "À jour" = aucune migration en attente (et non dbVersion === APP_VERSION) : la table version
        // ne reçoit une ligne que quand le schéma change
        $upToDate = $migrator->pendingVersions(APP_VERSION) === [];

        // Étape d'installation :
        //  - migrations   : jamais installé ;
        //  - organization : tables à jour, ni utilisateur ni organisme ;
        //  - user         : organisme créé, pas encore d'utilisateur.
        // Limite assumée : supprimer à la main tous les utilisateurs d'un site n'ayant qu'un organisme
        // rouvre l'étape "user" (aucune fonctionnalité de l'app ne permet de le faire).
        $step = match (true) {
            $dbVersion === Migrator::NOT_INSTALLED => 'migrations',
            $upToDate && User::count() === 0 => Organization::count() === 0 ? 'organization' : 'user',
            default => null,
        };

        $status = match (true) {
            $step !== null => self::INSTALL_REQUIRED,
            !$upToDate => self::UPDATE_REQUIRED,
            default => self::READY,
        };

        return new self($status, null, $step, $database, $migrator);
    }

    /**
     * Le cache de routes n'est possible qu'une fois l'app installée et à jour : tant que install/update
     * sont possibles, l'ensemble de routes change selon l'état de la base, et un cache figé sur le
     * mauvais ensemble resterait servi indéfiniment.
     */
    public function allowsRouteCache(): bool
    {
        return $this->status === self::READY;
    }

    /** @return class-string<\Yawasla\Routes\RouteProvider> */
    public function routes(): string
    {
        return match ($this->status) {
            self::CONFIG_REQUIRED => SetupRoutes::class,
            self::INSTALL_REQUIRED => InstallRoutes::class,
            self::UPDATE_REQUIRED => UpdateRoutes::class,
            self::UNAVAILABLE => UnavailableRoutes::class,
            default => AppRoutes::class,
        };
    }
}
