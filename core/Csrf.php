<?php
declare(strict_types=1);

namespace Core;

/**
 * Protection CSRF par jeton de session, vérifié sur chaque POST.
 */
final class Csrf
{
    private const CLE = '_csrf_token';

    public static function token(): string
    {
        Auth::start();
        if (empty($_SESSION[self::CLE])) {
            $_SESSION[self::CLE] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::CLE];
    }

    public static function check(?string $token = null): bool
    {
        Auth::start();
        $token ??= (string) ($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $attendu = (string) ($_SESSION[self::CLE] ?? '');
        return $attendu !== '' && $token !== '' && hash_equals($attendu, $token);
    }

    public static function verifyOrFail(): void
    {
        if (self::check()) {
            return;
        }
        Logger::error('Jeton CSRF invalide sur ' . ($_SERVER['REQUEST_URI'] ?? '?'));
        if (Request::isJson()) {
            Response::json(['success' => false, 'message' => 'Jeton de sécurité invalide. Rechargez la page.'], 419);
        }
        Flash::add('danger', 'Jeton de sécurité invalide ou expiré. Rechargez la page.');
        Response::back();
    }

    /** Champ caché prêt à insérer dans un formulaire. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
