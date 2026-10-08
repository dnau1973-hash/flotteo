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

        Flash::add('success', 'Bienvenue sur Flotteo, ' . (Auth::user()['nom'] ?? 'utilisateur') . '.');
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
        $this->render('auth/profil', ['utilisateur' => Auth::user(), 'base_url' => $this->baseUrl()]);
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
            if (strlen($nouveau) < 8) {
                Flash::add('danger', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
                $this->redirect('/profil');
            }
            if ($nouveau !== $confirmation) {
                Flash::add('danger', 'La confirmation du nouveau mot de passe ne correspond pas.');
                $this->redirect('/profil');
            }
            User::updatePassword($id, $nouveau);
        }

        // Après le mot de passe : un changement de mot de passe raté ne doit pas
        // laisser une image déposée jamais associée.
        $this->appliquerAvatar($id, $avatar, $suppression, (string) ($user['avatar'] ?? ''));

        Flash::add('success', 'Profil mis à jour.');
        $this->redirect('/profil');
    }
}
