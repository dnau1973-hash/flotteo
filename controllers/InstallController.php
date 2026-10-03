<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\InstallState;
use Core\Request;
use Core\Response;
use Core\Url;
use Services\Installer;

/**
 * Assistant d'installation initial.
 *
 * Double protection :
 *   1. `InstallState::status()` intercepte le bootstrap et refuse l'accès à toute
 *      route autre que l'assistant tant que l'application n'est pas installée ;
 *   2. ce contrôleur refuse de s'exécuter si le verrou existe déjà.
 *
 * L'assistant ne requiert aucune session authentifiée — il ne s'exécute que sur
 * une application non installée — mais chaque tentative est limitée par un
 * temporisateur glissant pour freiner le brute-force des identifiants BDD.
 */
final class InstallController extends Controller
{
    private const CLE_SESSION   = '_install_tentatives';
    private const FENETRE       = 300;   // 5 minutes
    private const MaxTentatives = 12;

    /** Formulaire de l'assistant. */
    public function index(): void
    {
        $etat = InstallState::status();
        if ($etat['etat'] === InstallState::ETAT_INSTALLE) {
            $this->refuserReinstallation();
        }

        $this->afficher([
            'etat'   => $etat,
            'erreur' => null,
        ]);
    }

    /** Test de connexion AJAX — validation de l'étape 1. */
    public function tester(): void
    {
        if (InstallState::estInstalle()) {
            $this->ko('L\'application est déjà installée.', 403);
        }
        if (!Csrf::check()) {
            $this->ko('Jeton de sécurité invalide. Rechargez la page.', 419);
        }
        if (!self::consommerTentative()) {
            $this->ko('Trop de tentatives successives. Patientez quelques minutes.', 429);
        }

        $db    = self::parametresBdd();
        $test  = Installer::tester($db);
        $verif = $test['succes']
            ? Installer::verifierBase($db)
            : ['succes' => false, 'message' => $test['message']];

        $succes = $test['succes'] && $verif['succes'];

        $this->json([
            'success' => $succes,
            'message' => $succes ? $verif['message'] : ($test['succes'] ? $verif['message'] : $test['message']),
            'detail'  => $test['succes'] ? $test['detail'] : '',
        ], $succes ? 200 : 422);
    }

    /** Exécution de l'installation — étapes 2 et 3. */
    public function executer(): void
    {
        if (InstallState::estInstalle()) {
            $this->refuserReinstallation();
        }
        Csrf::verifyOrFail();
        if (!self::consommerTentative()) {
            $this->afficher([
                'etat'   => InstallState::status(),
                'erreur' => 'Trop de tentatives successives. Patientez quelques minutes avant de réessayer.',
            ]);
            return;
        }

        $db    = self::parametresBdd();
        $admin = [
            // Le nom d'utilisateur est stocké dans `utilisateurs.nom` : le schéma
            // ne comporte ni colonne `username` ni colonne `login`, et l'e-mail
            // reste l'identifiant de connexion (contrainte d'unicité uq_utilisateurs_email).
            'nom'        => trim((string) Request::input('nom_d_utilisateur', '')),
            'email'      => trim((string) Request::input('admin_email', '')),
            'motdepasse' => (string) Request::input('admin_password', ''),
        ];

        if ((string) Request::input('admin_password_confirm', '') !== $admin['motdepasse']) {
            $this->afficher([
                'etat'     => InstallState::status(),
                'erreur'   => 'La confirmation du mot de passe ne correspond pas.',
            ]);
            return;
        }

        $resultat = Installer::executer($db, $admin);

        if (!$resultat['succes']) {
            $this->afficher([
                'etat'      => InstallState::status(),
                'erreur'    => $resultat['message'],
                'realisees' => $resultat['etapes'],
            ]);
            return;
        }

        Flash::add('success', 'Installation terminée. Connectez-vous avec le compte administrateur créé.');
        Response::redirect(Url::to('/login'));
    }

    // ---------------------------------------------------------------- helpers

    /** Paramètres de connexion lus depuis le formulaire, avec valeurs par défaut. */
    private static function parametresBdd(): array
    {
        return [
            'host'     => trim((string) Request::input('db_host', 'localhost')),
            'port'     => Request::int('db_port', 3306),
            'database' => trim((string) Request::input('db_name', 'flotteo')),
            'username' => trim((string) Request::input('db_username', 'flotteo')),
            // Consommé ici, jamais journalisé ni réaffiché par la vue.
            'password' => (string) Request::input('db_password', ''),
        ];
    }

    /** Temporisateur de session à fenêtre glissante. */
    private static function consommerTentative(): bool
    {
        Auth::start();
        $maintenant = time();
        $tentatives = array_values(array_filter(
            $_SESSION[self::CLE_SESSION] ?? [],
            static fn ($t): bool => is_int($t) && $t > $maintenant - self::FENETRE
        ));

        if (count($tentatives) >= self::MaxTentatives) {
            $_SESSION[self::CLE_SESSION] = $tentatives;
            return false;
        }

        $tentatives[]                      = $maintenant;
        $_SESSION[self::CLE_SESSION]       = $tentatives;

        return true;
    }

    /** L'application étant installée, toute réinitialisation est refusée. */
    private function refuserReinstallation(): never
    {
        Flash::add('warning', 'Flotteo est déjà installé. La réinitialisation est désactivée par sécurité.');
        Response::redirect(Url::to('/login'));
    }

    /**
     * Valeurs de l'étape 1 à réafficher après un re-rendu serveur.
     *
     * Sans cela, toute erreur renvoyée depuis l'étape 2 réinitialiserait les
     * champs de connexion à leurs valeurs par défaut et obligerait l'utilisateur
     * à tout ressaisir. Le mot de passe MySQL est délibérément absent : un secret
     * ne doit jamais être réémis dans une réponse HTML.
     *
     * @return array{host: string, port: string, database: string, username: string}
     */
    private static function saisieBdd(): array
    {
        return [
            'host'     => trim((string) Request::input('db_host', 'localhost')),
            'port'     => trim((string) Request::input('db_port', '3306')),
            'database' => trim((string) Request::input('db_name', 'flotteo')),
            'username' => trim((string) Request::input('db_username', 'flotteo')),
        ];
    }

    /** Rend la vue autonome de l'assistant. */
    private function afficher(array $donnees): void
    {
        $this->render('install/index', $donnees + [
            'base_url' => Url::base(),
            'etapes'   => Installer::tablesAttendues(),
            'saisie'   => self::saisieBdd(),
            'admin'    => trim((string) Request::input('nom_d_utilisateur', '')),
            'version'  => (string) ((require dirname(__DIR__) . '/config/config.php')['app']['version'] ?? '1.0.0'),
        ], false);
    }
}