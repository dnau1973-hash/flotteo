<?php
declare(strict_types=1);

namespace Core;

/**
 * Dépôt de pièces jointes : nommage unique, contrôle MIME réel, dossier protégé.
 */
final class Upload
{
    /**
     * Enregistre un fichier téléversé.
     *
     * @param array<string, mixed> $fichier      Entrée de `$_FILES`.
     * @param string $dossier                      Sous-dossier de `public/uploads`.
     * @param array<string, string>|null $mimes    Types autorisés pour cet appel ;
     *                                               null = toute la configuration.
     *
     * @return array{message: ?string, chemin: ?string, mime: ?string, taille: ?int, erreur: ?string}
     */
    public static function store(array $fichier, string $dossier, ?array $mimes = null): array
    {
        $vide = ['message' => null, 'chemin' => null, 'mime' => null, 'taille' => null, 'erreur' => null];

        $code = (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code === UPLOAD_ERR_NO_FILE) {
            return $vide;
        }
        if ($code !== UPLOAD_ERR_OK) {
            Logger::error("Echec upload ($code) sur $dossier");
            return ['message' => null, 'chemin' => null, 'mime' => null, 'taille' => null,
                    'erreur' => 'Transfert interrompu, réessayez.'];
        }

        $conf  = (require dirname(__DIR__) . '/config/config.php');
        $taille = (int) ($fichier['size'] ?? 0);
        if ($taille <= 0 || $taille > (int) $conf['securite']['taille_max_upload']) {
            return ['message' => null, 'chemin' => null, 'mime' => null, 'taille' => null,
                    'erreur' => 'Fichier trop volumineux (8 Mo maximum).'];
        }

        // Un sous-ensemble explicite ne peut jamais élargir la configuration : on
        // l'intersecte avec elle. Un avatar refuse ainsi un PDF même si le
        // contrôleur passa par erreur une liste trop permissive.
        $autorises = $conf['securite']['mime_autorises'];
        if ($mimes !== null) {
            $autorises = array_intersect_key($autorises, $mimes);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
        if (!isset($autorises[$mime])) {
            Logger::error("MIME refusé: $mime");
            return ['message' => null, 'chemin' => null, 'mime' => null, 'taille' => null,
                    'erreur' => 'Type de fichier non autorisé ('
                        . implode(', ', array_map('strtoupper', array_values($autorises))) . ').'];
        }

        $dossierCible = rtrim((string) $conf['uploads']['chemin'], '/') . '/' . trim($dossier, '/');
        if (!is_dir($dossierCible) && !@mkdir($dossierCible, 0775, true) && !is_dir($dossierCible)) {
            Logger::error("Dossier upload inaccessible: $dossierCible");
            return ['message' => null, 'chemin' => null, 'mime' => null, 'taille' => null,
                    'erreur' => 'Stockage indisponible.'];
        }

        $nom = bin2hex(random_bytes(16)) . '.' . $autorises[$mime];
        $dest = $dossierCible . '/' . $nom;
        if (!move_uploaded_file($fichier['tmp_name'], $dest)) {
            Logger::error("move_uploaded_file a échoué pour $dossierCible");
            return ['message' => null, 'chemin' => null, 'mime' => null, 'taille' => null,
                    'erreur' => 'Enregistrement du fichier impossible.'];
        }
        @chmod($dest, 0644);

        return ['message' => null, 'chemin' => $nom, 'mime' => $mime, 'taille' => $taille, 'erreur' => null];
    }

    /** Chemin absolu d'un fichier stocké, ou null. */
    public static function absolutePath(string $dossier, string $nom): ?string
    {
        if (preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $nom) !== 1) {
            return null;
        }
        $base = rtrim((require dirname(__DIR__) . '/config/config.php')['uploads']['chemin'], '/');
        $path = $base . '/' . trim($dossier, '/') . '/' . $nom;
        return is_file($path) ? $path : null;
    }

    /** Supprime un fichier stocké. */
    public static function remove(string $dossier, string $nom): void
    {
        $path = self::absolutePath($dossier, $nom);
        if ($path !== null) {
            @unlink($path);
        }
    }
}
