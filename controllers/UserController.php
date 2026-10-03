<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Core\Upload;
use Models\User;


/**
 * Administration des utilisateurs (CRUD complet, rôle Administration).
 */
final class UserController extends Controller
{
    public function index(): void
    {
        $this->guard(Auth::ROLE_ADMIN);
        $this->view()->useScript('utilisateurs.js');
        $this->render('admin/utilisateurs', [
            'base_url'    => $this->baseUrl(),
            'utilisateurs' => User::all(),
            'roles'       => User::ROLES,
            'moi'         => Auth::id(),
        ]);
    }

    /** Création ou mise à jour d'un utilisateur (POST). */
    public function save(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        $id    = Request::int('id');
        $nom   = mb_substr((string) Request::input('nom', ''), 0, 120);
        $email = mb_substr((string) Request::input('email', ''), 0, 190);
        $role  = (string) Request::input('role', 'lecture_seule');
        $actif = Request::int('actif') === 1 ? 1 : 0;
        $pwd   = (string) Request::input('password', '');

        $erreurs = [];
        if ($nom === '') {
            $erreurs[] = 'Le nom est obligatoire.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'Adresse email invalide.';
        }
        if (!array_key_exists($role, User::ROLES)) {
            $erreurs[] = 'Rôle inconnu.';
        }
        if (User::emailExists($email, $id > 0 ? $id : null)) {
            $erreurs[] = 'Cet email est déjà utilisé.';
        }
        if ($id === 0 && strlen($pwd) < 8) {
            $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($id > 0 && $pwd !== '' && strlen($pwd) < 8) {
            $erreurs[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        }

        // Garde-fou : ne jamais supprimer ou rétrograder le dernier administrateur actif.
        $perteAdmin = $id > 0 && $this->estDernierAdmin($id, $role, $actif);
        if ($perteAdmin) {
            $erreurs[] = 'Impossible : ce compte est le dernier administrateur actif.';
        }

        if ($erreurs !== []) {
            $this->repondreErreurs($erreurs);
        }

        // L'utilisateur visé est résolu **avant** tout dépôt : un identifiant
        // inexistant doit être refusé sans laisser de fichier sur le disque.
        $existant = $id > 0 ? User::find($id) : null;
        if ($id > 0 && $existant === null) {
            $this->repondreErreurs(['Utilisateur introuvable.']);
        }

        // L'avatar est traité avant l'écriture : un dépôt refusé ne doit pas
        // laisser un compte à moitié modifié. Le nouveau fichier n'est retenu en
        // base qu'après l'écriture, pour qu'un échec n'ait pas déjà fait
        // disparaître l'image encore en service.
        $avatar      = $this->deposerAvatar($erreurs);
        $suppression = Request::int('avatar_supprimer') === 1;

        if ($erreurs !== []) {
            $this->repondreErreurs($erreurs);
        }

        try {
            if ($id > 0) {
                User::update($id, $nom, $email, $role, $actif);
                if ($pwd !== '') {
                    User::updatePassword($id, $pwd);
                }

                $this->appliquerAvatar($id, $avatar, $suppression, (string) ($existant['avatar'] ?? ''));
                Flash::add('success', 'Utilisateur mis à jour.');
            } else {
                // À la création, `avatar_supprimer` n'a rien à supprimer : le
                // dépôt éventuel a déjà été transmis à `User::create()`.
                User::create($nom, $email, $pwd, $role, $avatar);
                Flash::add('success', 'Utilisateur créé.');
            }
        } catch (\PDOException $e) {
            Core\Logger::error('Enregistrement utilisateur impossible', $e);
            // Le fichier éventuellement déposé reste orphelin : on le retire
            // plutôt que de le laisser sur le disque sans compte associé.
            if ($avatar !== null) {
                Upload::remove(User::DOSSIER_AVATAR, $avatar);
            }
            $this->repondreErreurs(['Enregistrement impossible.']);
        }

        $this->redirect('/admin/utilisateurs');
    }

    /**
     * Dépose un avatar téléversé et retourne son nom de fichier, ou null.
     *
     * Les erreurs de dépôt sont ajoutées à `$erreurs` : l'appelant abandonne
     * l'enregistrement, l'utilisateur voit la raison précise — format refusé,
     * fichier trop lourd — au lieu d'un message générique.
     */
    private function deposerAvatar(array &$erreurs): ?string
    {
        $fichier = $_FILES['avatar'] ?? null;
        if (!is_array($fichier) || !isset($fichier['error']) || (int) $fichier['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $depot = Upload::store($fichier, User::DOSSIER_AVATAR, User::MIMES_AVATAR);
        if ($depot['erreur'] !== null) {
            $erreurs[] = 'Avatar : ' . mb_strtolower((string) $depot['erreur']);

            return null;
        }

        return $depot['chemin'];
    }

    /**
     * Écrit l'avatar en base et retire l'ancien fichier devenu inutile.
     *
     * Un fichier nouvellement déposé l'emporte sur la case de suppression :
     * l'interface masque cette option dès qu'un fichier est choisi,
     * pour que la demande ne soit jamais formulée par l'utilisateur.
     *
     * @param ?string $avatar      Nouveau fichier déposé, ou null
     * @param bool    $suppression L'utilisateur a demandé la suppression
     * @param string  $precedent   Nom du fichier actuellement enregistré
     */
    private function appliquerAvatar(int $id, ?string $avatar, bool $suppression, string $precedent): void
    {
        $nouveau = $avatar ?? ($suppression ? null : $precedent);

        // Rien à écrire : ni nouveau fichier, ni suppression demandée.
        if ($avatar === null && !$suppression) {
            return;
        }
        if ($nouveau === $precedent) {
            return;
        }

        User::setAvatar($id, $nouveau);

        if ($precedent !== '' && $precedent !== $nouveau) {
            Upload::remove(User::DOSSIER_AVATAR, $precedent);
        }
    }

    /** Suppression d'un utilisateur (POST). */
    public function delete(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);
        $id = Request::int('id');

        if ($id === Auth::id()) {
            Flash::add('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            $this->redirect('/admin/utilisateurs');
        }
        if ($this->estDernierAdmin($id, 'lecture_seule', 0)) {
            Flash::add('danger', 'Suppression impossible : dernier administrateur actif du système.');
            $this->redirect('/admin/utilisateurs');
        }

        try {
            User::delete($id);
            Flash::add('success', 'Utilisateur supprimé.');
        } catch (\PDOException $e) {
            Core\Logger::error("Suppression utilisateur #$id impossible", $e);
            Flash::add('danger', 'Suppression impossible.');
        }

        $this->redirect('/admin/utilisateurs');
    }

    /** Le compte identifié est-il le dernier administrateur actif de la plateforme ? */
    private function estDernierAdmin(int $id, string $nouveauRole, int $nouveauActif): bool
    {
        $u = User::find($id);
        if ($u === null || (string) $u['role'] !== Auth::ROLE_ADMIN || (int) $u['actif'] !== 1) {
            return false;
        }
        $resteAdmin = $nouveauRole === Auth::ROLE_ADMIN && $nouveauActif === 1;
        return $resteAdmin ? false : User::countAdminsActifs($id) === 0;
    }

    /** @param string[] $erreurs */
    private function repondreErreurs(array $erreurs): never
    {
        $message = implode(' ', $erreurs);
        if (Request::isJson()) {
            $this->ko($message);
        }
        Flash::add('danger', $message);
        $this->redirect('/admin/utilisateurs');
    }
}
