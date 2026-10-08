<?php
declare(strict_types=1);

namespace Core;

use Models\User;

/**
 * Contrôleur de base : helpers de rendu, redirection et contrôle d'accès.
 */
abstract class Controller
{
    /**
     * Instance de vue de l'action courante, mémorisée.
     *
     * `view()` doit toujours rendre **la même** instance : `useScript()` et
     * `share()` y déposent des données que `render()` relit ensuite. Retourner une
     * instance neuve à chaque appel — l'usage naturel depuis un contrôleur —
     * faisait déposer ces données sur un objet que personne ne relit, sans la
     * moindre erreur. Le script de page n'était donc jamais émis, et le module
     * d'édition d'un utilisateur s'ouvrait vide.
     */
    private ?View $vue = null;

    protected function view(): View
    {
        return $this->vue ??= new View();
    }

    protected function baseUrl(): string
    {
        return Url::base();
    }

    /**
     * Rend une vue. `$layout = false` produit une page autonome, sans
     * l'en-tête ni le pied de page applicatifs (assistant d'installation).
     */
    protected function render(string $template, array $data = [], bool $layout = true): void
    {
        $this->view()->render($template, $data, $layout);
    }

    protected function redirect(string $chemin): never
    {
        Response::redirect(Url::to($chemin));
    }

    protected function json(array $data, int $code = 200): never
    {
        Response::json($data, $code);
    }

    protected function ok(string $message, array $extra = []): never
    {
        $this->json(['success' => true, 'message' => $message] + $extra);
    }

    protected function ko(string $message, int $code = 422, array $extra = []): never
    {
        $this->json(['success' => false, 'message' => $message] + $extra, $code);
    }

    /** Exige une authentification + un niveau de rôle. */
    protected function guard(string $role = Auth::ROLE_LECTURE): void
    {
        Auth::requireRole($role);
    }

    /** Enveloppe une action d'écriture (CSRF + rôle). */
    protected function guardPost(string $role = Auth::ROLE_MODIFICATION): void
    {
        Csrf::verifyOrFail();
        Auth::requireRole($role);
    }

    // --- Téléversement d'avatars ---------------------------------------------
    //
    // Ces trois helpers vivent ici, et non dans un contrôleur, parce que deux
    // contrôleurs en ont besoin : l'édition d'un utilisateur par un
    // administrateur, et la page de profil personnel. Dupliquer le dépôt, le
    // remplacement et le retrait de l'ancien fichier dans les deux aurait
    // garanti que les deux copies divergent.

    /**
     * Dépose un avatar téléversé et retourne son nom de fichier, ou null.
     *
     * Les erreurs de dépôt sont ajoutées à `$erreurs` : l'appelant abandonne
     * l'enregistrement, l'utilisateur voit la raison précise — format refusé,
     * fichier trop lourd — au lieu d'un message générique.
     */
    protected function deposerAvatar(array &$erreurs): ?string
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

        return (string) $depot['chemin'];
    }

    /**
     * Écrit l'avatar en base et retire l'ancien fichier devenu inutile.
     *
     * Un fichier nouvellement déposé l'emporte sur la case de suppression :
     * l'interface masque cette option dès qu'un fichier est choisi, pour que
     * la demande ne soit jamais formulée par l'utilisateur.
     *
     * @param ?string $avatar      Nouveau fichier déposé, ou null
     * @param bool    $suppression L'utilisateur a demandé la suppression
     * @param string  $precedent   Nom du fichier actuellement enregistré
     */
    protected function appliquerAvatar(int $id, ?string $avatar, bool $suppression, string $precedent): void
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

    /**
     * Refuse un envoi tronqué par `post_max_size`.
     *
     * À appeler **avant** `guardPost()` : au-delà de `post_max_size`, PHP ne
     * remplit ni `$_POST` ni `$_FILES`, et le contrôle CSRF échouerait sur un
     * jeton absent — un message trompeur, qui accuse la session au lieu de
     * l'image trop lourde. Ret : le fichier n'est jamais parvenu au serveur.
     *
     * @return string|null Message à signaler, ou null si l'envoi est complet
     */
    protected function envoiTronque(): ?string
    {
        if (!Request::corpsTronque()) {
            return null;
        }

        $limite = (string) Request::postMaxSize();
        Logger::error("Formulaire utilisateur tronque par post_max_size ($limite)");

        return sprintf(
            "Fichier refusé : la taille de l'envoi dépasse la limite du serveur (%s). "
            . 'Choisissez une image plus légère.',
            $limite
        );
    }
}
