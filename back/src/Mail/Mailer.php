<?php

declare(strict_types=1);

namespace Yawasla\Mail;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use Yawasla\Core\Config;

/**
 * Envoi des mails transactionnels (sécurité : lien de connexion, réinitialisation), volontairement
 * indépendant de Brevo : la connexion admin ne doit pas dépendre du provider de diffusion.
 * SMTP si le DSN SMTP est renseigné (recommandé), sinon la fonction mail() native de PHP.
 */
final class Mailer
{
    public function __construct(private Config $config, private LoggerInterface $logger)
    {
    }

    /** Retourne false en cas d'échec (loggé) plutôt que de lever : l'appelant décide de la suite. */
    public function send(string $to, string $subject, string $text, ?string $html = null): bool
    {
        $mail = new PHPMailer(true);

        try {
            $this->configureTransport($mail);

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($this->config->mailFromAddress, $this->config->mailFromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;

            if ($html !== null) {
                $mail->isHTML(true);
                $mail->Body = $html;
                $mail->AltBody = $text;
            } else {
                $mail->Body = $text;
            }

            return $mail->send();
        } catch (Exception $exception) {
            // ErrorInfo de PHPMailer, plus explicite que le message de l'exception
            $this->logger->error('Envoi de mail impossible.', ['to' => $to, 'error' => $mail->ErrorInfo]);

            return false;
        }
    }

    /**
     * DSN SMTP sur une ligne au format schema://utilisateur:motdepasse:hôte:port (schema smtps = SSL,
     * smtp = STARTTLS). Vide ou illisible : repli sur mail() natif (un mail de sécurité doit partir
     * même si le DSN est mal formé). Le mot de passe peut contenir des « : » : port et hôte sont lus
     * par la fin, l'utilisateur par le début, le reste au milieu est le mot de passe.
     */
    private function configureTransport(PHPMailer $mail): void
    {
        if ($this->config->smtp === '') {
            $mail->isMail();

            return;
        }

        [$scheme, $rest] = str_contains($this->config->smtp, '://')
            ? explode('://', $this->config->smtp, 2)
            : ['smtp', $this->config->smtp];
        $parts = explode(':', $rest);

        if (count($parts) < 4 || !ctype_digit($parts[count($parts) - 1])) {
            $this->logger->warning('DSN SMTP illisible, repli sur mail() natif.');
            $mail->isMail();

            return;
        }

        $port = (int) array_pop($parts);
        $host = array_pop($parts);
        $user = array_shift($parts);
        $password = implode(':', $parts);

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $password;
        $mail->SMTPSecure = $scheme === 'smtps' ? 'ssl' : 'tls';
    }
}
