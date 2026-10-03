<?php
declare(strict_types=1);

namespace Seed;

/**
 * Mot de passe des comptes de démonstration.
 *
 * Argon2id est utilisé lorsqu'il est disponible, sinon bcrypt. L'algorithme
 * retenu est publié dans le fichier SQL généré afin que la valeur en clair
 * documentée corresponde toujours au condensat présent en base.
 */
final class MdpDemo
{
    /** Valeur en clair : publication assumée, ce jeu est réservé au développement. */
    public static function valeur(): string
    {
        return 'Password123!';
    }

    /** @return string constante PHP de l'algorithme de hachage */
    public static function algorithme(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /** Libellé lisible de l'algorithme, pour les commentaires du fichier SQL. */
    public static function libelle(): string
    {
        return self::algorithme() === PASSWORD_ARGON2ID ? 'Argon2id' : 'bcrypt';
    }
}
