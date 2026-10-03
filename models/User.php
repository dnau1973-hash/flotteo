<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Url;

/**
 * Accès aux utilisateurs et à la matrice des rôles.
 */
final class User
{
    public const ROLES = [
        'lecture_seule' => 'Lecture Seule',
        'modification'  => 'Modification',
        'administration' => 'Administration',
    ];

    /** Sous-dossier de `public/uploads` où sont déposés les avatars. */
    public const DOSSIER_AVATAR = 'avatars';

    /**
     * Types acceptés pour un avatar : images uniquement.
     *
     * La liste est transmise à `Core\Upload::store()` qui l'intersecte avec la
     * configuration générale — un PDF ne peut donc pas être déposé comme avatar,
     * même si cette liste venait à être élargie par erreur.
     */
    public const MIMES_AVATAR = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM utilisateurs WHERE id = :id', ['id' => $id]);
    }

    /** Recherche par email (insensible à la casse via collation). */
    public static function findByLogin(string $identifiant): ?array
    {
        return Database::one('SELECT * FROM utilisateurs WHERE email = :e LIMIT 1', ['e' => $identifiant]);
    }

    public static function all(): array
    {
        return Database::all('SELECT * FROM utilisateurs ORDER BY nom ASC');
    }

    public static function create(string $nom, string $email, string $motDePasse, string $role, ?string $avatar = null): int
    {
        return Database::insert('utilisateurs', [
            'nom'           => $nom,
            'email'         => $email,
            'password_hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
            'role'          => $role,
            'actif'         => 1,
            'avatar'        => $avatar,
        ]);
    }

    /**
     * Met à jour l'identité, le rôle et l'état.
     *
     * L'avatar est délibérément hors de cette méthode : `Core\Database::update()`
     * écrit toutes les clés fournies, `null` compris. Inclure l'avatar ici
     * effacerait donc l'image à chaque enregistrement de profil. Son traitement
     * est isolé dans `setAvatar()`.
     */
    public static function update(int $id, string $nom, string $email, string $role, int $actif): int
    {
        return Database::update('utilisateurs', $id, [
            'nom'   => $nom,
            'email' => $email,
            'role'  => $role,
            'actif' => $actif,
        ]);
    }

    /** Remplace ou efface l'avatar. Passer null retire l'image. */
    public static function setAvatar(int $id, ?string $avatar): int
    {
        return Database::update('utilisateurs', $id, ['avatar' => $avatar]);
    }

    /** URL publique de l'avatar, ou null si l'utilisateur n'en a pas. */
    public static function avatarUrl(mixed $avatar): ?string
    {
        $nom = (string) ($avatar ?? '');
        if ($nom === '') {
            return null;
        }

        return Url::upload(self::DOSSIER_AVATAR . '/' . $nom);
    }

    /**
     * Initiales de repli, affichées quand aucun avatar n'est enregistré.
     *
     * La coupure suit les espaces, les tirets et les apostrophes, afin que
     * « Jean-Pierre Dupont » donne JD et non JP. Deux mots ou plus : première
     * lettre du premier, première lettre du dernier. Un seul mot : sa première
     * lettre seule — « Marc » donne M, pas MA, qui se lirait comme une faute.
     */
    public static function initiales(string $nom): string
    {
        $mots = preg_split('/[\s\-\'’]+/u', trim($nom), -1, PREG_SPLIT_NO_EMPTY);
        if ($mots === [] || $mots === false) {
            return '?';
        }
        if (count($mots) === 1) {
            return mb_strtoupper((string) mb_substr($mots[0], 0, 1));
        }

        $premier = (string) mb_substr($mots[0], 0, 1);
        $dernier = (string) mb_substr((string) end($mots), 0, 1);

        return mb_strtoupper($premier . $dernier);
    }

    public static function updatePassword(int $id, string $motDePasse): int
    {
        return Database::update('utilisateurs', $id, [
            'password_hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
        ]);
    }

    /**
     * Supprime un compte et son avatar.
     *
     * Le nom du fichier est lu **avant** la suppression : la ligne disparaît, la
     * valeur ne serait plus récupérable ensuite. Si l'effacement du fichier
     * échoue, l'orphan reste sur le disque mais aucun compte ne pointe dessus —
     * l'inverse laisserait un avatar cassé sans page à l'afficher.
     */
    public static function delete(int $id): int
    {
        $avatar = self::avatarDe($id);

        $supprime = Database::delete('utilisateurs', $id);
        if ($supprime > 0 && $avatar !== null && $avatar !== '') {
            \Core\Upload::remove(self::DOSSIER_AVATAR, $avatar);
        }

        return $supprime;
    }

    /** Nom du fichier d'avatar enregistré, ou null. */
    private static function avatarDe(int $id): ?string
    {
        $valeur = Database::scalar('SELECT avatar FROM utilisateurs WHERE id = :id', ['id' => $id], null);

        return $valeur === null ? null : (string) $valeur;
    }

    /** Nombre d'administrateurs actifs : protège le dernier compte d'administration. */
    public static function countAdminsActifs(?int $horsId = null): int
    {
        $sql = "SELECT COUNT(*) FROM utilisateurs WHERE role = 'administration' AND actif = 1";
        $params = [];
        if ($horsId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $horsId;
        }
        return (int) Database::scalar($sql, $params, 0);
    }

    public static function emailExists(string $email, ?int $horsId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM utilisateurs WHERE email = :e';
        $params = ['e' => $email];
        if ($horsId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $horsId;
        }
        return (int) Database::scalar($sql, $params, 0) > 0;
    }
}
