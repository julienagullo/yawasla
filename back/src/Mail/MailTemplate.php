<?php

declare(strict_types=1);

namespace Yawasla\Mail;

use InvalidArgumentException;

/**
 * Modèles de mails transactionnels, seul endroit où le back traduit (les textes vivent dans
 * templates/{locale}.php). `create()` rend sujet, corps texte et corps HTML pour une langue donnée,
 * avec interpolation des variables {clé}. Langue inconnue : repli sur la langue par défaut.
 */
final class MailTemplate
{
    public const DEFAULT_LOCALE = 'fr';

    /**
     * @param array<string, string|int> $vars
     * @return array{subject: string, text: string, html: ?string}
     */
    public function create(string $name, string $locale, array $vars = []): array
    {
        $templates = $this->load($locale);

        if (!isset($templates[$name])) {
            throw new InvalidArgumentException("Modèle de mail inconnu : {$name}.");
        }

        // Remplacement en une seule passe (strtr) : une valeur contenant {autre} n'est pas ré-interpolée
        $replace = [];
        foreach ($vars as $key => $value) {
            $replace['{' . $key . '}'] = (string) $value;
        }

        $template = $templates[$name];
        $html = isset($template['html']) ? $this->wrap($template['html']) : null;

        return [
            'subject' => strtr($template['subject'], $replace),
            'text' => strtr($template['text'], $replace),
            'html' => $html !== null ? strtr($html, $replace) : null,
        ];
    }

    /** Habillage HTML commun (design minimal, styles en ligne car les clients mail ignorent <style>). */
    private function wrap(string $body): string
    {
        return '<div style="background:#f4f4f5;padding:24px 12px;font-family:Helvetica,Arial,sans-serif">'
            . '<div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:10px;padding:28px 28px 20px;color:#1f2937;line-height:1.55;font-size:15px">'
            . $body
            . '</div>'
            . '<p style="max-width:480px;margin:14px auto 0;color:#9ca3af;font-size:12px;text-align:center">{site}</p>'
            . '</div>';
    }

    /** @return array<string, array{subject: string, text: string, html?: string}> */
    private function load(string $locale): array
    {
        $file = __DIR__ . '/templates/' . $locale . '.php';

        if (!is_file($file)) {
            $file = __DIR__ . '/templates/' . self::DEFAULT_LOCALE . '.php';
        }

        return require $file;
    }
}
