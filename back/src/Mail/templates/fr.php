<?php

declare(strict_types=1);

// Textes des mails transactionnels en français. Variables interpolées : voir MailTemplate::create().
// Bouton HTML réutilisable (styles en ligne).
$button = static fn (string $url, string $label): string =>
    '<p style="margin:24px 0"><a href="' . $url . '" style="display:inline-block;background:#166534;color:#ffffff;'
    . 'text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600">' . $label . '</a></p>';

return [
    'password_reset' => [
        'subject' => 'Réinitialisation de votre mot de passe',
        'text' => "As-salâmu ʿalaykum {name},\n\n"
            . "Vous avez demandé un nouveau mot de passe sur {site}.\n\n"
            . "Pour en choisir un nouveau, ouvrez ce lien (valable 15 minutes) :\n{link}\n\n"
            . "Si vous n'êtes pas à l'origine de cette demande, veuillez ignorer ce message.",
        'html' => '<p>As-salâmu ʿalaykum <strong>{name}</strong>,</p>'
            . '<p>Vous avez demandé un nouveau mot de passe sur <strong>{site}</strong>.</p>'
            . $button('{link}', 'Nouveau mot de passe')
            . '<p style="color:#6b7280;font-size:13px">Ce lien est valable 15 minutes. '
            . "Si vous n'êtes pas à l'origine de cette demande, veuillez ignorer ce message.</p>",
    ],
    'login_alert' => [
        'subject' => 'Tentatives de connexion suspectes sur votre espace',
        'text' => "As-salâmu ʿalaykum {name},\n\n"
            . "Plusieurs tentatives de connexion ont échoué sur {site}.\n"
            . "Adresse IP : {ip}\nDate : {datetime}\n\n"
            . "L'accès depuis cette adresse est temporairement bloqué. Si vous êtes à l'origine de "
            . "ces tentatives, veuillez ignorer ce message.",
        'html' => '<p>As-salâmu ʿalaykum <strong>{name}</strong>,</p>'
            . '<p>Plusieurs tentatives de connexion ont échoué sur <strong>{site}</strong>.</p>'
            . '<p style="background:#f9fafb;border-radius:8px;padding:12px 14px;font-size:14px">'
            . 'Adresse IP : <strong>{ip}</strong><br>Date : <strong>{datetime}</strong></p>'
            . '<p style="color:#6b7280;font-size:13px">'
            . "L'accès depuis cette adresse est temporairement bloqué. Si vous êtes à l'origine de ces "
            . "tentatives, veuillez ignorer ce message.</p>",
    ],
];
