<?php
declare(strict_types=1);

namespace Seed;

/**
 * Traduction des codes d'erreur PDO en messages exploitables.
 *
 * Aucune trace technique n'est exposée : seule la cause utile est retournée, le
 * détail complet restant dans le journal applicatif.
 */
final class Message
{
    public static function pdo(\PDOException $e, string $phase): string
    {
        $code = $e->errorInfo[1] ?? 0;

        return match ((int) $code) {
            1044, 1045 => sprintf('phase %s : accès refusé à la base (utilisateur ou mot de passe MySQL incorrect).', $phase),
            1049      => sprintf('phase %s : la base n\'existe pas.', $phase),
            2002      => sprintf('phase %s : serveur de base de données injoignable (hôte ou port incorrect).', $phase),
            2006      => sprintf('phase %s : la connexion à la base a été perdue.', $phase),
            default   => sprintf('phase %s : opération refusée par le serveur de base de données.', $phase),
        };
    }
}
