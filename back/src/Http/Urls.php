<?php

declare(strict_types=1);

namespace Yawasla\Http;

/**
 * Chemins publics de l'application. Le chemin de base est déduit de l'emplacement de index.php :
 * vide à la racine d'un domaine, "/yawasla" si l'app est servie depuis un sous-dossier ou un alias.
 */
final class Urls
{
    public function __construct(public string $basePath, public string $apiBase)
    {
    }

    /** @param array<string, mixed> $server */
    public static function fromServer(array $server, string $apiPrefix): self
    {
        $basePath = rtrim(str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? ''))), '/');

        return new self($basePath, $basePath . $apiPrefix);
    }
}
