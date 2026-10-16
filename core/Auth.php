<?php
declare(strict_types=1);

namespace Core;

/**
 * Authentification, session durcie et matrice des rôles.
 */
final class Auth
{
    public const ROLE_LECTURE        = 'lecture_seule';
    public const ROLE_COMPTABILITE   = 'comptabilite';
    public const ROLE_DOCUMENTALISTE = 'documentaliste';
    public const ROLE_MODIFICATION   = 'modification';
    public const ROLE_ADMIN          = 'administration';

    private const HIERARCHIE = [
        self::ROLE_LECTURE        => 1,
        self::ROLE_COMPTABILITE   => 2,
        self::ROLE_DOCUMENTALISTE => 3,
        self::ROLE_MODIFICATION   => 4,
        self::ROLE_ADMIN          => 5,
    ];

    private static ?array $user = null;

    /** Démarre la session avec des cookies durcis. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $conf = (require dirname(__DIR__) . '/config/config.php')['session'];
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';

        session_name($conf['nom']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => $https,
        ]);
        session_start();
    }

    public static function attempt(string $identifiant, string $motDePasse): bool
    {
        $utilisateur = \Models\User::findByLogin($identifiant);
        if ($utilisateur === null || (int) $utilisateur['actif'] !== 1) {
            return false;
        }
        if (!password_verify($motDePasse, (string) $utilisateur['password_hash'])) {
            return false;
        }
        if (password_needs_rehash((string) $utilisateur['password_hash'], PASSWORD_DEFAULT)) {
            \Models\User::updatePassword((int) $utilisateur['id'], $motDePasse);
        }
        self::login($utilisateur);
        return true;
    }

    public static function login(array $utilisateur): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id']    = (int) $utilisateur['id'];
        $_SESSION['role']       = (string) $utilisateur['role'];
        $_SESSION['last_active'] = time();
        self::$user = $utilisateur;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    /** Utilisateur courant authentifié, ou null. */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $u = \Models\User::find((int) $_SESSION['user_id']);
        if ($u === null || (int) $u['actif'] !== 1) {
            self::logout();
            return null;
        }
        self::$user = $u;
        return $u;
    }

    public static function refresh(): ?array
    {
        self::$user = null;
        return self::user();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u === null ? null : (int) $u['id'];
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u === null ? null : (string) $u['role'];
    }

    /** Le rôle courant atteint-il le niveau exigé ? */
    public static function can(string $roleRequis): bool
    {
        $role = self::role();
        if ($role === null) {
            return false;
        }
        return (self::HIERARCHIE[$role] ?? 0) >= (self::HIERARCHIE[$roleRequis] ?? 99);
    }

    /** L'utilisateur courant possède-t-il le rôle administrateur ? */
    public static function isAdmin(): bool
    {
        return self::can(self::ROLE_ADMIN);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (Request::isJson()) {
                Response::json(['success' => false, 'message' => 'Session expirée.'], 401);
            }
            Flash::add('warning', 'Veuillez vous connecter pour accéder à l\'application.');
            Response::redirect(Url::to('/login'));
        }

        $u = self::user();
        if ($u !== null && ((int) ($u['doit_changer_mot_de_passe'] ?? 0) === 1 || \Models\User::isPasswordExpired($u))) {
            $currentPath = Router::currentPath();
            if ($currentPath !== '/changer-mot-de-passe-requis' && $currentPath !== '/logout') {
                if (Request::isJson()) {
                    Response::json(['success' => false, 'message' => 'Changement de mot de passe obligatoire.'], 403);
                }
                Response::redirect(Url::to('/changer-mot-de-passe-requis'));
            }
        }
    }

    public static function requireRole(string $roleRequis): void
    {
        self::requireLogin();
        if (!self::can($roleRequis)) {
            if (Request::isJson()) {
                Response::json(['success' => false, 'message' => 'Droits insuffisants.'], 403);
            }
            http_response_code(403);
            (new View())->render('erreur/403', [], false);
            exit;
        }
    }

    /** L'utilisateur courant possède-t-il le rôle Comptabilité ? */
    public static function isComptabilite(): bool
    {
        return self::role() === self::ROLE_COMPTABILITE;
    }

    /** L'utilisateur peut-il modifier cet incident ? */
    public static function canEditIncident(): bool
    {
        return self::can(self::ROLE_MODIFICATION) || self::isComptabilite();
    }

    /** L'utilisateur peut-il créer un nouvel incident ? */
    public static function canCreateIncident(): bool
    {
        return self::can(self::ROLE_MODIFICATION);
    }

    /** L'utilisateur peut-il archiver/supprimer un incident ? */
    public static function canDeleteIncident(): bool
    {
        return self::can(self::ROLE_MODIFICATION);
    }

    /** L'utilisateur peut-il téléverser une pièce jointe sur un incident ? */
    public static function canUploadIncidentDocument(): bool
    {
        return self::can(self::ROLE_DOCUMENTALISTE) || self::isComptabilite();
    }

    /** L'utilisateur peut-il supprimer une pièce jointe d'incident ? */
    public static function canDeleteIncidentDocument(): bool
    {
        return self::can(self::ROLE_MODIFICATION);
    }

    /** Exige l'autorisation d'éditer ou créer un incident. */
    public static function requireIncidentPermission(bool $isCreation): void
    {
        self::requireLogin();
        $autorise = $isCreation ? self::canCreateIncident() : self::canEditIncident();
        if (!$autorise) {
            if (Request::isJson()) {
                Response::json(['success' => false, 'message' => 'Droits insuffisants.'], 403);
            }
            http_response_code(403);
            (new View())->render('erreur/403', [], false);
            exit;
        }
    }

    /** Exige l'autorisation de téléverser des documents d'incident. */
    public static function requireUploadIncidentDocument(): void
    {
        self::requireLogin();
        if (!self::canUploadIncidentDocument()) {
            if (Request::isJson()) {
                Response::json(['success' => false, 'message' => 'Droits insuffisants.'], 403);
            }
            http_response_code(403);
            (new View())->render('erreur/403', [], false);
            exit;
        }
    }
}
