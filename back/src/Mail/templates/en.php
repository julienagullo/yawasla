<?php

declare(strict_types=1);

// Transactional email texts in English. Interpolated variables: see MailTemplate::create().
$button = static fn (string $url, string $label): string =>
    '<p style="margin:24px 0"><a href="' . $url . '" style="display:inline-block;background:#166534;color:#ffffff;'
    . 'text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600">' . $label . '</a></p>';

return [
    'password_reset' => [
        'subject' => 'Reset your password',
        'text' => "As-salamu alaykum {name},\n\n"
            . "You requested a new password on {site}.\n\n"
            . "To choose a new one, open this link (valid for 15 minutes):\n{link}\n\n"
            . "If you did not request this, please ignore this message.",
        'html' => '<p>As-salamu alaykum <strong>{name}</strong>,</p>'
            . '<p>You requested a new password on <strong>{site}</strong>.</p>'
            . $button('{link}', 'New password')
            . '<p style="color:#6b7280;font-size:13px">This link is valid for 15 minutes. '
            . "If you did not request this, please ignore this message.</p>",
    ],
    'login_alert' => [
        'subject' => 'Suspicious sign-in attempts on your account',
        'text' => "As-salamu alaykum {name},\n\n"
            . "Several sign-in attempts have failed on {site}.\n"
            . "IP address: {ip}\nDate: {datetime}\n\n"
            . "Access from this address is temporarily blocked. If these attempts were you, please "
            . "ignore this message.",
        'html' => '<p>As-salamu alaykum <strong>{name}</strong>,</p>'
            . '<p>Several sign-in attempts have failed on <strong>{site}</strong>.</p>'
            . '<p style="background:#f9fafb;border-radius:8px;padding:12px 14px;font-size:14px">'
            . 'IP address: <strong>{ip}</strong><br>Date: <strong>{datetime}</strong></p>'
            . '<p style="color:#6b7280;font-size:13px">'
            . "Access from this address is temporarily blocked. If these attempts were you, please "
            . "ignore this message.</p>",
    ],
];
