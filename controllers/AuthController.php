<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Core\Response;
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

    /** Formulaire de profil (nom, email, mot de passe). */
    public function profile(): void
    {
        $this->guard();
        $this->render('auth/profil', ['utilisateur' => Auth::user(), 'base_url' => $this->baseUrl()]);
    }

    /** Mise à jour du profil et changement de mot de passe. */
    public function updateProfile(): void
    {
        $this->guardPost();
        $user = Auth::user();
        $id   = (int) $user['id'];

        $nom   = mb_substr((string) Request::input('nom', ''), 0, 120);
        $email = mb_substr((string) Request::input('email', ''), 0, 190);

        if ($nom === '' || $email === '') {
            Flash::add('danger', 'Le nom et l\'email sont obligatoires.');
            Response::back();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::add('danger', 'Format d\'email invalide.');
            Response::back();
        }
        if (User::emailExists($email, $id)) {
            Flash::add('danger', 'Cet email est déjà attribué à un autre utilisateur.');
            Response::back();
        }

        $actif = (int) $user['actif'];
        User::update($id, $nom, $email, (string) $user['role'], $actif);

        $ancien = (string) Request::input('password_actuel', '');
        $nouveau = (string) Request::input('password_nouveau', '');
        $confirmation = (string) Request::input('password_confirmation', '');

        if ($nouveau !== '' || $confirmation !== '') {
            if (!password_verify($ancien, (string) $user['password_hash'])) {
                Flash::add('danger', 'Le mot de passe actuel est incorrect.');
                Response::back();
            }
            if (strlen($nouveau) < 8) {
                Flash::add('danger', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
                Response::back();
            }
            if ($nouveau !== $confirmation) {
                Flash::add('danger', 'La confirmation du nouveau mot de passe ne correspond pas.');
                Response::back();
            }
            User::updatePassword($id, $nouveau);
        }

        Flash::add('success', 'Profil mis à jour.');
        $this->redirect('/profil');
    }
}
