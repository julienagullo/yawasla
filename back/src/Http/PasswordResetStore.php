<?php

declare(strict_types=1);

namespace Yawasla\Http;

use RuntimeException;
use Yawasla\Core\Paths;

/**
 * Jetons de réinitialisation de mot de passe, à usage unique et à courte durée de vie, dans un
 * fichier (jetons hachés) plutôt qu'en base : aucune migration. Les entrées expirées sont purgées.
 */
final class PasswordResetStore
{
    private const TTL = 900; // 15 minutes

    public function __construct(private Paths $paths)
    {
    }

    /** Émet un jeton pour l'utilisateur et retourne sa valeur en clair (à envoyer par mail). */
    public function issue(int $userId): string
    {
        $token = bin2hex(random_bytes(32));

        $this->update(static function (array $entries) use ($token, $userId): array {
            $entries[self::key($token)] = ['user_id' => $userId, 'expires' => time() + self::TTL];

            return $entries;
        });

        return $token;
    }

    /** Un jeton non expiré existe-t-il déjà pour cet utilisateur ? (anti-renvoi : 1 mail / durée de vie) */
    public function hasActiveFor(int $userId): bool
    {
        $active = false;

        $this->update(static function (array $entries) use ($userId, &$active): array {
            foreach ($entries as $entry) {
                if ($entry['user_id'] === $userId) {
                    $active = true;
                    break;
                }
            }

            return $entries;
        });

        return $active;
    }

    /** Vérifie et consomme le jeton (usage unique). Retourne l'id utilisateur, ou null si absent/expiré. */
    public function consume(string $token): ?int
    {
        $userId = null;

        $this->update(static function (array $entries) use ($token, &$userId): array {
            $entry = $entries[self::key($token)] ?? null;

            if ($entry !== null) {
                $userId = $entry['user_id'];
                unset($entries[self::key($token)]);
            }

            return $entries;
        });

        return $userId;
    }

    private static function key(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Lecture/écriture sous verrou exclusif, entrées expirées purgées au passage.
     *
     * @param callable(array<string, array{user_id: int, expires: int}>): array<string, array{user_id: int, expires: int}> $change
     */
    private function update(callable $change): void
    {
        $handle = fopen($this->paths->tempDir() . '/password_resets.json', 'c+');
        if ($handle === false) {
            throw new RuntimeException('Impossible d\'ouvrir le fichier des jetons de réinitialisation.');
        }

        try {
            flock($handle, LOCK_EX);
            $entries = json_decode((string) stream_get_contents($handle), true);
            $entries = array_filter(
                is_array($entries) ? $entries : [],
                static fn (array $entry): bool => $entry['expires'] > time(),
            );

            $entries = $change($entries);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($entries));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
