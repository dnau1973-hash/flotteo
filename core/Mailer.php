<?php
declare(strict_types=1);

namespace Core;

/**
 * Envoi d'emails.
 *
 * Deux transports sont possibles, selon le paramètre `mail_transport` :
 *  - `mail`  : frontal PHP natif, avec repli journalisé ;
 *  - `smtp`  : serveur externe, dialogue implémenté par Core\SmtpClient.
 *
 * Aucun mot de passe ni secret n'est exposé dans les logs applicatifs.
 */
final class Mailer
{
    /** Transports proposés dans l'interface d'administration. */
    public const TRANSPORTS = ['mail', 'smtp'];

    public static function send(string $destinataire, string $sujet, string $corpsHtml): bool
    {
        if (self::transport() === 'smtp') {
            return self::sendSmtp($destinataire, $sujet, $corpsHtml);
        }

        return self::sendNatif($destinataire, $sujet, $corpsHtml);
    }

    /** Frontend natif mail(). */
    private static function sendNatif(string $destinataire, string $sujet, string $corpsHtml): bool
    {
        $conf       = (require dirname(__DIR__) . '/config/config.php');
        $expediteur = (string) self::param('email_expediteur', 'no-reply@flotteo.local');
        $frontiere  = 'b3f1c2a4-' . bin2hex(random_bytes(8));

        $entetes = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $conf['app']['nom'] . ' <' . $expediteur . '>',
            'X-Mailer: Flotteo/' . $conf['app']['version'],
        ]);

        $ok = @mail($destinataire, '=?UTF-8?B?' . base64_encode($sujet) . '?=', $corpsHtml, $entetes);
        if (!$ok) {
            Logger::error("Echec d'envoi du mail [$sujet] vers $destinataire (frontiere $frontiere)");
        }

        return $ok;
    }

    /** Transport SMTP externe. */
    private static function sendSmtp(string $destinataire, string $sujet, string $corpsHtml): bool
    {
        try {
            $client = new SmtpClient(
                (string) self::param('smtp_host', ''),
                (int) self::param('smtp_port', 587),
                (string) self::param('smtp_chiffrement', 'tls'),
                (string) self::param('smtp_user', ''),
                (string) self::param('smtp_password', '')
            );
        } catch (\InvalidArgumentException $e) {
            Logger::error('Configuration SMTP invalide : ' . $e->getMessage());
            return false;
        }

        $expediteur = (string) self::param('email_expediteur', 'no-reply@flotteo.local');

        return $client->send($expediteur, $destinataire, $sujet, $corpsHtml);
    }

    /** Transport actuellement retenu. */
    public static function transport(): string
    {
        $valeur = (string) self::param('mail_transport', 'mail');

        return in_array($valeur, self::TRANSPORTS, true) ? $valeur : 'mail';
    }

    /** Lecture d'un paramètre applicatif (table parametres). */
    public static function param(string $cle, mixed $defaut = null): mixed
    {
        try {
            $val = Database::scalar('SELECT valeur FROM parametres WHERE cle = :c', ['c' => $cle]);
            return $val ?? $defaut;
        } catch (\PDOException $e) {
            Logger::error("Lecture parametre $cle impossible", $e);
            return $defaut;
        }
    }
}
