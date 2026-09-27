<?php

declare(strict_types=1);

namespace Yawasla\Http;

use RuntimeException;
use Yawasla\Core\Paths;

/**
 * Réponse graduée aux tentatives de connexion, par IP, sans jamais annoncer à l'attaquant qu'il est
 * repéré :
 *  - < 10 échecs   : OK, connexion normale ;
 *  - 10 à 99       : TARPIT, la requête part dans un cul-de-sac (pause, puis même réponse qu'un mot de
 *                    passe faux) — ses connexions s'enlisent, un bon mot de passe n'est jamais confirmé ;
 *  - >= 100        : BLOCKED, cul-de-sac instantané (plus de pause : on cesse d'immobiliser un worker
 *                    par requête, fail2ban prend le relais au niveau pare-feu).
 * Fenêtre glissante de 1 h : dès l'arrêt des tentatives, le compteur repart de zéro.
 * Stocké dans un fichier (IP hachées) plutôt qu'en base : aucune migration, rien à configurer.
 */
final class LoginThrottle
{
    public const OK = 'ok';
    public const TARPIT = 'tarpit';
    public const BLOCKED = 'blocked';

    private const TARPIT_THRESHOLD = 10;
    private const BLOCK_THRESHOLD = 100;
    private const WINDOW_SECONDS = 3600;

    public function __construct(private Paths $paths)
    {
    }

    /** @return self::OK|self::TARPIT|self::BLOCKED */
    public function status(string $ip): string
    {
        $failures = $this->update(static fn (array $entries): array => $entries)[self::key($ip)]['failures'] ?? 0;

        return $this->classify($failures);
    }

    /**
     * Enregistre un échec et retourne le nouveau statut. Le passage de TARPIT à BLOCKED signale le
     * franchissement du seuil (comparer au statut lu avant l'appel pour n'agir qu'une fois).
     *
     * @return self::OK|self::TARPIT|self::BLOCKED
     */
    public function fail(string $ip): string
    {
        $failures = 0;

        $this->update(static function (array $entries) use ($ip, &$failures): array {
            $current = $entries[self::key($ip)]['failures'] ?? 0;
            // Plafonné au seuil de blocage : compteur borné, mais `last` toujours rafraîchi → tant que
            // l'attaquant insiste, la fenêtre d'1 h ne se referme jamais
            $failures = min($current + 1, self::BLOCK_THRESHOLD);
            $entries[self::key($ip)] = ['failures' => $failures, 'last' => time()];

            return $entries;
        });

        return $this->classify($failures);
    }

    /** @return self::OK|self::TARPIT|self::BLOCKED */
    private function classify(int $failures): string
    {
        return match (true) {
            $failures >= self::BLOCK_THRESHOLD => self::BLOCKED,
            $failures >= self::TARPIT_THRESHOLD => self::TARPIT,
            default => self::OK,
        };
    }

    public function clear(string $ip): void
    {
        $this->update(static function (array $entries) use ($ip): array {
            unset($entries[self::key($ip)]);

            return $entries;
        });
    }

    private static function key(string $ip): string
    {
        return hash('sha256', $ip);
    }

    /**
     * Lecture/écriture sous verrou exclusif (requêtes simultanées), entrées expirées purgées au passage.
     *
     * @param callable(array<string, array{failures: int, last: int}>): array<string, array{failures: int, last: int}> $change
     * @return array<string, array{failures: int, last: int}>
     */
    private function update(callable $change): array
    {
        $handle = fopen($this->paths->tempDir() . '/login_attempts.json', 'c+');
        if ($handle === false) {
            throw new RuntimeException('Impossible d\'ouvrir le fichier des tentatives de connexion.');
        }

        try {
            flock($handle, LOCK_EX);
            $entries = json_decode((string) stream_get_contents($handle), true);
            $entries = array_filter(
                is_array($entries) ? $entries : [],
                static fn (array $entry): bool => time() - $entry['last'] < self::WINDOW_SECONDS,
            );

            $entries = $change($entries);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($entries));

            return $entries;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
