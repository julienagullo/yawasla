<?php

declare(strict_types=1);

namespace Yawasla\Core;

use RuntimeException;

/**
 * Passerelle PHP → React : sert le index.html compilé par Vite (copié dans resources/app.html par
 * le script de release) en y injectant, à la place du marqueur, un <base href> et les données de
 * démarrage lues par le front (src/app/boot.ts). public/index.php reste ainsi l'unique point d'entrée.
 */
final class AppShell
{
    public const MARKER = '<!-- yawasla:boot -->';

    public function __construct(private string $templatePath)
    {
    }

    /** Absent en dev : le front est servi par le serveur Vite. */
    public function exists(): bool
    {
        return is_file($this->templatePath);
    }

    /**
     * @param string $basePath chemin de base de l'app ("" à la racine, "/yawasla" en sous-dossier)
     * @param array<string, mixed> $boot données de démarrage (voir Boot côté front)
     */
    public function render(string $basePath, array $boot): string
    {
        $html = (string) file_get_contents($this->templatePath);

        if (!str_contains($html, self::MARKER)) {
            throw new RuntimeException('Marqueur "' . self::MARKER . '" absent de ' . $this->templatePath . '.');
        }

        // JSON_HEX_* : une valeur contenant "</script>" ne peut pas refermer la balise
        $json = json_encode(
            $boot,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        // <base> en premier : les URL relatives du build (assets, favicon) en dépendent
        $head = '<base href="' . htmlspecialchars($basePath . '/', ENT_QUOTES) . '" />'
            . "\n    " . '<script id="yawasla-boot" type="application/json">' . $json . '</script>';

        return str_replace(self::MARKER, $head, $html);
    }
}
