<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Core\View;
use Models\AuditLog;
use Models\User;

/**
 * Connexion, déconnexion et gestion du profil personnel.
 */
final class AuthController extends Controller
{
    /** Écran de connexion. */
    public function form(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        (new View())->render('auth/login', ['base_url' => $this->baseUrl()], false);
    }

    /** Traitement de l'authentification. */
    public function login(): void
    {
        Csrf::verifyOrFail();

        $identifiant = (string) Request::input('email', '');
        $motDePasse  = (string) Request::input('password', '');

        if ($identifiant === '' || $motDePasse === '') {
            Flash::add('danger', 'Identifiant et mot de passe obligatoires.');
            $this->redirect('/login');
        }

        if (!Auth::attempt($identifiant, $motDePasse)) {
            Logger::error('Echec de connexion pour ' . $identifiant);
            Flash::add('danger', 'Identifiants invalides ou compte désactivé.');
            $this->redirect('/login');
        }

        $user = Auth::user();
        $statusMdp = User::passwordExpirationStatus($user);

        if ($statusMdp['expire']) {
            if ($statusMdp['expire_par_delai']) {
                Flash::add('warning', 'Votre mot de passe a expiré (validité maximale de 6 mois). Vous devez obligatoirement le renouveler pour accéder à l\'application.');
            } else {
                Flash::add('warning', 'Pour des raisons de sécurité, vous devez renouveler votre mot de passe pour continuer.');
            }
            $this->redirect('/changer-mot-de-passe-requis');
        }

        // Rappel un mois avant la date d'échéance semestrielle :
        if ($statusMdp['en_rappel']) {
            $jours = $statusMdp['jours_restants'];
            $dateFin = $statusMdp['date_expiration'] ? $statusMdp['date_expiration']->format('d/m/Y') : '';
            Flash::add('warning', "Rappel de sécurité : votre mot de passe expire dans {$jours} jour(s) (le {$dateFin}). Pensez à le renouveler prochainement depuis votre profil.");
        }

        Flash::add('success', 'Bienvenue sur Flotteo, ' . ($user['nom'] ?? 'utilisateur') . '.');
        $this->redirect('/dashboard');
    }

    /** Déconnexion. */
    public function logout(): void
    {
        Csrf::verifyOrFail();
        Auth::logout();
        $this->redirect('/login');
    }

    /** Formulaire de profil (nom, email, mot de passe, avatar). */
    public function profile(): void
    {
        $this->guard();
        $user = Auth::user();
        $statusMdp = User::passwordExpirationStatus($user);
        $this->render('auth/profil', [
            'utilisateur' => $user,
            'status_mdp'  => $statusMdp,
            'base_url'    => $this->baseUrl(),
        ]);
    }

    /**
     * Mise à jour du profil, changement de mot de passe et avatar.
     *
     * Le rôle `ROLE_LECTURE` est exigé, et non `ROLE_MODIFICATION` : changer
     * son propre nom, son email ou sa photo n'est pas une modification de la
     * flotte, et un compte en lecture seule doit pouvoir corriger sa propre
     * identité. La protection CSRF, elle, reste complète.
     */
    public function updateProfile(): void
    {
        // Avant `guardPost()` : au-delà de `post_max_size`, `$_POST` est vide et
        // le contrôle CSRF échouerait sur un jeton absent, en accusationnant la
        // session au lieu de l'image trop lourde.
        $tronque = $this->envoiTronque();
        if ($tronque !== null) {
            Flash::add('danger', $tronque);
            $this->redirect('/profil');
        }

        $this->guardPost(Auth::ROLE_LECTURE);
        $user = Auth::user();
        $id   = (int) $user['id'];

        $nom   = mb_substr((string) Request::input('nom', ''), 0, 120);
        $email = mb_substr((string) Request::input('email', ''), 0, 190);

        $erreurs = [];
        if ($nom === '' || $email === '') {
            $erreurs[] = 'Le nom et l\'email sont obligatoires.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'Format d\'email invalide.';
        }
        if ($email !== '' && User::emailExists($email, $id)) {
            $erreurs[] = 'Cet email est déjà attribué à un autre utilisateur.';
        }

        /*
         * L'avatar est déposé avant toute écriture, et retenu pour la base
         * seulement après : un fichier refusé ne doit pas laisser un profil à
         * moitié modifié, et un échec d'écriture ne doit pas avoir fait
         * disparaître l'image encore en service.
         */
        $avatar      = $this->deposerAvatar($erreurs);
        $suppression = Request::int('avatar_supprimer') === 1;

        if ($erreurs !== []) {
            Flash::add('danger', implode(' ', $erreurs));
            $this->redirect('/profil');
        }

        $actif = (int) $user['actif'];
        try {
            User::update($id, $nom, $email, (string) $user['role'], $actif);
        } catch (\PDOException $e) {
            Logger::error('Mise à jour du profil impossible', $e);
            // Le fichier éventuellement déposé resterait orphelin sur le disque.
            if ($avatar !== null) {
                \Core\Upload::remove(User::DOSSIER_AVATAR, $avatar);
            }
            Flash::add('danger', 'Enregistrement impossible.');
            $this->redirect('/profil');
        }

        $ancien = (string) Request::input('password_actuel', '');
        $nouveau = (string) Request::input('password_nouveau', '');
        $confirmation = (string) Request::input('password_confirmation', '');

        if ($nouveau !== '' || $confirmation !== '') {
            if (!password_verify($ancien, (string) $user['password_hash'])) {
                Flash::add('danger', 'Le mot de passe actuel est incorrect.');
                $this->redirect('/profil');
            }
            if (mb_strlen($nouveau) < 10) {
                Flash::add('danger', 'Le nouveau mot de passe doit comporter au moins 10 caractères.');
                $this->redirect('/profil');
            }
            if (!preg_match('/[a-z]/', $nouveau)) {
                Flash::add('danger', 'Le mot de passe doit comporter au moins une lettre minuscule.');
                $this->redirect('/profil');
            }
            if (!preg_match('/[A-Z]/', $nouveau)) {
                Flash::add('danger', 'Le mot de passe doit comporter au moins une lettre majuscule.');
                $this->redirect('/profil');
            }
            if (!preg_match('/[0-9]/', $nouveau)) {
                Flash::add('danger', 'Le mot de passe doit comporter au moins un chiffre.');
                $this->redirect('/profil');
            }
            if (!preg_match('/[^a-zA-Z0-9]/', $nouveau)) {
                Flash::add('danger', 'Le mot de passe doit comporter au moins un caractère spécial.');
                $this->redirect('/profil');
            }
            if ($ancien !== '' && $ancien === $nouveau) {
                Flash::add('danger', 'Le nouveau mot de passe doit être différent de l\'ancien mot de passe.');
                $this->redirect('/profil');
            }
            if ($nouveau !== $confirmation) {
                Flash::add('danger', 'La confirmation du nouveau mot de passe ne correspond pas.');
                $this->redirect('/profil');
            }
            User::updatePassword($id, $nouveau);
            Auth::refresh();
        }

        // Après le mot de passe : un changement de mot de passe raté ne doit pas
        // laisser une image déposée jamais associée.
        $this->appliquerAvatar($id, $avatar, $suppression, (string) ($user['avatar'] ?? ''));

        AuditLog::modification(
            'utilisateur',
            $id,
            null,
            "Mise à jour du profil personnel ($nom)",
            [
                'nom'                  => $nom,
                'email'                => $email,
                'mot_de_passe_modifie' => ($nouveau !== ''),
                'avatar_modifie'       => ($avatar !== null || $suppression),
            ]
        );

        Flash::add('success', 'Profil mis à jour.');
        $this->redirect('/profil');
    }

    /** Écran imposé de renouvellement du mot de passe. */
    public function formForcePasswordChange(): void
    {
        $this->guard();
        $user = Auth::user();
        $statusMdp = User::passwordExpirationStatus($user);
        if (!$statusMdp['expire']) {
            $this->redirect('/dashboard');
        }

        (new View())->render('auth/force_password_change', [
            'utilisateur' => $user,
            'status_mdp'  => $statusMdp,
            'base_url'    => $this->baseUrl(),
        ], false);
    }

    /** Traitement du renouvellement forcé du mot de passe (POST). */
    public function submitForcePasswordChange(): void
    {
        Csrf::verifyOrFail();
        $this->guard();
        $user = Auth::user();
        $id = (int) $user['id'];

        $actuel       = (string) Request::input('password_actuel', '');
        $nouveau      = (string) Request::input('password_nouveau', '');
        $confirmation = (string) Request::input('password_confirmation', '');

        $erreurs = [];

        // 1. Vérification du mot de passe actuel
        if (!password_verify($actuel, (string) $user['password_hash'])) {
            $erreurs[] = 'Le mot de passe actuel est incorrect.';
        }

        // 2. Règles de complexité et de longueur
        if (mb_strlen($nouveau) < 10) {
            $erreurs[] = 'Le nouveau mot de passe doit comporter au moins 10 caractères.';
        }
        if (!preg_match('/[a-z]/', $nouveau)) {
            $erreurs[] = 'Le mot de passe doit comporter au moins une lettre minuscule.';
        }
        if (!preg_match('/[A-Z]/', $nouveau)) {
            $erreurs[] = 'Le mot de passe doit comporter au moins une lettre majuscule.';
        }
        if (!preg_match('/[0-9]/', $nouveau)) {
            $erreurs[] = 'Le mot de passe doit comporter au moins un chiffre.';
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $nouveau)) {
            $erreurs[] = 'Le mot de passe doit comporter au moins un caractère spécial.';
        }

        // 3. Différent de l'ancien
        if ($actuel !== '' && $actuel === $nouveau) {
            $erreurs[] = 'Le nouveau mot de passe doit être différent de l\'ancien mot de passe.';
        }

        // 4. Confirmation
        if ($nouveau !== $confirmation) {
            $erreurs[] = 'La confirmation du nouveau mot de passe ne correspond pas.';
        }

        if ($erreurs !== []) {
            Flash::add('danger', implode(' ', $erreurs));
            $this->redirect('/changer-mot-de-passe-requis');
        }

        // Mise à jour du mot de passe et réinitialisation de l'exigence
        User::updatePasswordAndClearFlag($id, $nouveau);
        Auth::refresh();

        // Traçabilité dans le journal d'audit
        AuditLog::modification(
            'utilisateur',
            $id,
            null,
            "Renouvellement obligatoire du mot de passe effectué avec succès (" . ($user['email'] ?? '') . ")"
        );

        Flash::add('success', 'Votre mot de passe a été mis à jour avec succès. Bienvenue sur Flotteo !');
        $this->redirect('/dashboard');
    }
}
