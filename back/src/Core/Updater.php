<?php

declare(strict_types=1);

namespace Yawasla\Core;

use FilesystemIterator;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Mise à jour du code depuis le serveur de distribution (Config::$updateUrl). Le fichier version.json
 * (généré par scripts/release.mjs --release) décrit la dernière release :
 * { "version", "date", "php", "url", "sha256" }, "url" absolue ou relative à version.json.
 *
 * install() télécharge l'archive, vérifie son SHA-256 et écrase les fichiers de l'application (.env et
 * var/ ne sont jamais dans l'archive). public/index.php, qui porte APP_VERSION, est copié en dernier.
 * S'il y a des migrations, le site passe ensuite en "update_required" : l'administrateur les lance
 * depuis l'administration (UpdateRoutes).
 *
 * Trois codes d'erreur pour le front (failed, requirements, not_writable) : le détail reste dans le message.
 */
final class Updater
{
    /** Vérification en ligne au plus une fois par jour, une heure après un échec (serveur injoignable…) */
    private const CHECK_TTL = 86400;
    private const FAILED_CHECK_TTL = 3600;
    private const CHECK_TIMEOUT = 5;
    private const DOWNLOAD_TIMEOUT = 300;
    /** Dossier racine des archives produites par release.mjs */
    private const ARCHIVE_PREFIX = 'yawasla/';

    public function __construct(private Config $config, private Paths $paths, private LoggerInterface $logger)
    {
    }

    /**
     * Release plus récente que la version installée, ou null (à jour, désactivé, serveur injoignable).
     * Résultat en cache (vidé à chaque changement d'APP_VERSION, voir bootstrap).
     *
     * @return array{version: string, date: string, php: string, compatible: bool}|null
     */
    public function available(): ?array
    {
        if ($this->config->updateUrl === '') {
            return null;
        }

        $cacheFile = $this->paths->cacheDir() . '/release.json';
        $cache = json_decode((string) @file_get_contents($cacheFile), true);
        $ttl = ($cache['release'] ?? null) === null ? self::FAILED_CHECK_TTL : self::CHECK_TTL;

        if (!is_array($cache) || time() - (int) ($cache['checked_at'] ?? 0) > $ttl) {
            try {
                $release = $this->fetchRelease();
            } catch (Throwable $exception) {
                // Silencieux pour l'utilisateur : l'administration ne doit pas dépendre du serveur de distribution
                $this->logger->warning('Vérification des mises à jour impossible.', ['exception' => $exception]);
                $release = null;
            }

            $cache = ['checked_at' => time(), 'release' => $release];
            file_put_contents($cacheFile, json_encode($cache), LOCK_EX);
        }

        $release = $cache['release'];
        if (!is_array($release) || !$this->isNewer($release)) {
            return null;
        }

        return [
            'version' => $release['version'],
            'date' => $release['date'],
            'php' => $release['php'],
            'compatible' => $this->isCompatible($release),
        ];
    }

    /** @return string version installée */
    public function install(): string
    {
        // Sans resources/app.html, ce n'est pas une distribution (dépôt de dev) : on écraserait les sources
        if (!is_file($this->paths->shellTemplate())) {
            throw new ApiException('update.failed', 'Mise à jour automatique possible uniquement sur une installation issue d’une archive.');
        }

        if (!class_exists(ZipArchive::class)) {
            throw new ApiException('update.requirements', 'L’extension PHP zip est requise pour la mise à jour automatique.');
        }

        $lock = fopen($this->paths->tempDir() . '/update.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new ApiException('update.failed', 'Une mise à jour est déjà en cours.', [], 409);
        }

        // Écrasement des fichiers : ne pas s'interrompre si le navigateur est fermé ou si l'hébergeur limite la durée
        ignore_user_abort(true);
        @set_time_limit(0);

        $work = $this->paths->tempDir() . '/update';

        try {
            $release = $this->fetchRelease();

            if (!$this->isNewer($release)) {
                throw new ApiException('update.failed', 'L’application est déjà à jour.', [], 409);
            }

            if (!$this->isCompatible($release)) {
                throw new ApiException(
                    'update.requirements',
                    "Cette version nécessite PHP {$release['php']} ou supérieur."
                );
            }

            $this->removeDir($work);
            $this->ensureDir($work);

            $archive = $work . '/release.zip';
            $this->download($release['url'], $archive);

            if (!hash_equals($release['sha256'], (string) hash_file('sha256', $archive))) {
                throw new ApiException('update.failed', 'L’archive téléchargée est corrompue (empreinte SHA-256 différente).');
            }

            $files = $this->extract($archive, $work . '/files', $release['version']);
            $this->replaceFiles($work . '/files/' . rtrim(self::ARCHIVE_PREFIX, '/'), $files);

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            $this->logger->info('Mise à jour installée.', ['from' => APP_VERSION, 'to' => $release['version']]);

            return $release['version'];
        } finally {
            $this->removeDir($work);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * version.json validé, lu directement sur le serveur (sans le cache de available()).
     *
     * @return array{version: string, date: string, php: string, url: string, sha256: string}
     */
    private function fetchRelease(): array
    {
        $url = $this->config->updateUrl;
        if ($url === '') {
            throw new ApiException('update.failed', 'La vérification des mises à jour est désactivée.');
        }

        try {
            $data = json_decode($this->get($url), true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new ApiException('update.failed', 'Serveur de mises à jour injoignable.', [], 502, $exception);
        }

        $valid = is_array($data)
            && is_string($data['version'] ?? null) && preg_match('/^\d+\.\d+\.\d+$/', $data['version']) === 1
            && is_string($data['url'] ?? null) && $data['url'] !== ''
            && is_string($data['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $data['sha256']) === 1;

        if (!$valid) {
            throw new ApiException('update.failed', 'Fichier version.json invalide.', [], 502);
        }

        // URL relative : résolue par rapport à version.json (un miroir peut reprendre le fichier tel quel)
        $zipUrl = str_starts_with($data['url'], 'https://')
            ? $data['url']
            : substr($url, 0, (int) strrpos($url, '/') + 1) . ltrim($data['url'], '/');

        return [
            'version' => $data['version'],
            'date' => is_string($data['date'] ?? null) ? $data['date'] : '',
            'php' => is_string($data['php'] ?? null) ? $data['php'] : '8.0',
            'url' => $zipUrl,
            'sha256' => $data['sha256'],
        ];
    }

    /** @param array{version: string} $release */
    private function isNewer(array $release): bool
    {
        return version_compare($release['version'], APP_VERSION, '>');
    }

    /** @param array{php: string} $release */
    private function isCompatible(array $release): bool
    {
        return version_compare(PHP_VERSION, $release['php'], '>=');
    }

    private function get(string $url): string
    {
        $file = $this->paths->tempDir() . '/release-' . bin2hex(random_bytes(4)) . '.json';

        try {
            $this->transfer($url, $file, self::CHECK_TIMEOUT);

            return (string) file_get_contents($file);
        } finally {
            @unlink($file);
        }
    }

    private function download(string $url, string $file): void
    {
        try {
            $this->transfer($url, $file, self::DOWNLOAD_TIMEOUT);
        } catch (Throwable $exception) {
            throw new ApiException('update.failed', 'Téléchargement de la mise à jour impossible.', [], 502, $exception);
        }
    }

    /** HTTPS uniquement. cURL si disponible, sinon les flux PHP (certains hébergeurs désactivent l'un ou l'autre). */
    private function transfer(string $url, string $file, int $timeout): void
    {
        if (!str_starts_with($url, 'https://')) {
            throw new RuntimeException("URL de mise à jour refusée (HTTPS obligatoire) : {$url}");
        }

        $userAgent = 'Yawasla/' . APP_VERSION;

        if (function_exists('curl_init')) {
            $handle = fopen($file, 'wb');
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_FILE => $handle,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FAILONERROR => true,
                CURLOPT_CONNECTTIMEOUT => self::CHECK_TIMEOUT,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => $userAgent,
            ]);
            $ok = curl_exec($curl);
            $error = curl_error($curl);
            curl_close($curl);
            fclose($handle);

            if ($ok !== true) {
                throw new RuntimeException("Téléchargement de {$url} impossible : {$error}");
            }

            return;
        }

        $context = stream_context_create([
            'http' => ['timeout' => $timeout, 'user_agent' => $userAgent, 'follow_location' => 1, 'max_redirects' => 3],
        ]);

        if (!@copy($url, $file, $context)) {
            throw new RuntimeException("Téléchargement de {$url} impossible (cURL absent, allow_url_fopen désactivé ?).");
        }
    }

    /**
     * Extrait l'archive après avoir vérifié son contenu.
     *
     * @return list<string> chemins relatifs à la racine de l'application ("src/…", "public/index.php"…)
     */
    private function extract(string $archive, string $destination, string $version): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new ApiException('update.failed', 'Archive de mise à jour illisible.');
        }

        try {
            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                if (str_ends_with($name, '/')) {
                    continue;
                }

                $relative = substr($name, strlen(self::ARCHIVE_PREFIX));
                $unsafe = !str_starts_with($name, self::ARCHIVE_PREFIX)
                    || $relative === ''
                    || str_contains($name, '\\')
                    || in_array('..', explode('/', $relative), true);

                if ($unsafe) {
                    throw new ApiException('update.failed', "Chemin invalide dans l’archive : {$name}");
                }

                $files[] = $relative;
            }

            // L'archive doit correspondre à la version annoncée (version.json et archive publiés ensemble)
            $index = $zip->getFromName(self::ARCHIVE_PREFIX . 'public/index.php');
            $pattern = "/define\\('APP_VERSION',\\s*'" . preg_quote($version, '/') . "'\\)/";
            if (!is_string($index) || preg_match($pattern, $index) !== 1) {
                throw new ApiException('update.failed', "L’archive ne contient pas la version {$version}.");
            }

            if (!$zip->extractTo($destination)) {
                throw new ApiException('update.failed', 'Extraction de l’archive impossible.');
            }
        } finally {
            $zip->close();
        }

        return $files;
    }

    /**
     * Copie les fichiers extraits sur l'installation. Droits d'écriture vérifiés pour tous les fichiers
     * avant la première copie, index.php (APP_VERSION) copié en dernier.
     *
     * @param list<string> $files
     */
    private function replaceFiles(string $source, array $files): void
    {
        $publicDir = $this->publicDir();
        $target = function (string $relative) use ($publicDir): string {
            return str_starts_with($relative, 'public/')
                ? $publicDir . substr($relative, strlen('public'))
                : $this->paths->root . '/' . $relative;
        };

        foreach ($files as $relative) {
            if (!$this->isWritable($target($relative))) {
                throw new ApiException(
                    'update.not_writable',
                    "Fichier non modifiable par PHP : {$relative}"
                );
            }
        }

        usort($files, fn (string $a, string $b): int => ($a === 'public/index.php') <=> ($b === 'public/index.php'));

        foreach ($files as $relative) {
            $destination = $target($relative);
            $this->ensureDir(dirname($destination));

            if (!copy($source . '/' . $relative, $destination)) {
                // Installation partiellement mise à jour : l'archive peut être redéposée à la main
                $this->logger->error('Mise à jour interrompue pendant la copie des fichiers.', ['file' => $relative]);

                throw new ApiException('update.failed', "Copie impossible : {$relative}", [], 500);
            }
        }
    }

    /**
     * Dossier web réel : public/ peut avoir été renommé (OVH : www/, cPanel : public_html/). C'est celui
     * de l'index.php qui s'exécute, s'il est bien à la racine de l'application.
     */
    private function publicDir(): string
    {
        $script = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $dir = $script !== false ? dirname($script) : '';

        return $dir !== '' && basename($script) === 'index.php' && dirname($dir) === realpath($this->paths->root)
            ? $dir
            : $this->paths->root . '/public';
    }

    private function isWritable(string $path): bool
    {
        if (file_exists($path)) {
            return is_writable($path);
        }

        // Nouveau fichier : le premier dossier existant doit être modifiable
        do {
            $path = dirname($path);
        } while (!is_dir($path) && $path !== dirname($path));

        return is_writable($path);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Impossible de créer le dossier \"{$dir}\".");
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
