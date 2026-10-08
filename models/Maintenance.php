<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;
use Core\Request;
use Core\Upload;

/**
 * Historique des révisions et prestations d'entretien.
 */
final class Maintenance
{
    /** Sous-dossier de `public/uploads` où sont déposées les pièces jointes. */
    public const DOSSIER = 'maintenances';

    /**
     * Types de fichier acceptés en pièce jointe d'entretien.
     *
     * Le PDF est le format attendu d'une facture ou d'un devis ; les trois
     * images raster servent aux photos d'avant/après intervention. Aucune
     * valeur de `securite.mime_autorises` ne peut élargir cet ensemble :
     * `Upload::store()` n'en accepte qu'un sous-ensemble.
     */
    public const MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    /** Formats affichables comme vignette dans la liste des pièces jointes. */
    public const MIMES_VIGNETTE = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public const SELECT_BASE = '
        SELECT m.*, t.libelle AS type_libelle, t.categorie AS type_categorie,
               v.immatriculation, ma.nom AS marque_nom, mo.nom AS modele_nom
        FROM maintenances m
        INNER JOIN types_intervention t ON t.id = m.type_intervention_id
        INNER JOIN vehicules v ON v.id = m.vehicule_id
        INNER JOIN modeles mo ON mo.id = v.modele_id
        INNER JOIN marques  ma ON ma.id = mo.marque_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT_BASE . ' WHERE m.id = :id', ['id' => $id]);
    }

    /** Historique d'un véhicule ou liste globale filtrée. */
    public static function search(array $filtres = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filtres['vehicule_id'])) {
            $where[] = 'm.vehicule_id = :v';
            $params['v'] = (int) $filtres['vehicule_id'];
        }
        if (!empty($filtres['categorie'])) {
            $where[] = 't.categorie = :c';
            $params['c'] = (string) $filtres['categorie'];
        }
        if (!empty($filtres['du'])) {
            $where[] = 'm.date_operation >= :du';
            $params['du'] = (string) $filtres['du'];
        }
        if (!empty($filtres['au'])) {
            $where[] = 'm.date_operation <= :au';
            $params['au'] = (string) $filtres['au'];
        }

        $sql = self::SELECT_BASE;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        try {
            return Database::all($sql . ' ORDER BY m.date_operation DESC, m.id DESC', $params);
        } catch (\PDOException $e) {
            Logger::error('Lecture de l\'historique entretien impossible', $e);
            return [];
        }
    }

    public static function filtersFromRequest(): array
    {
        return [
            'vehicule_id' => Request::int('vehicule_id'),
            'categorie'   => (string) Request::input('categorie', ''),
            'du'          => Request::date('du'),
            'au'          => Request::date('au'),
        ];
    }

    public static function buildPayload(): array
    {
        $ht  = Request::float('cout_ht');
        $ttc = Request::float('cout_ttc');
        return [
            'vehicule_id'          => Request::int('vehicule_id'),
            'type_intervention_id' => Request::int('type_intervention_id'),
            'date_operation'       => Request::date('date_operation'),
            'kilometrage'          => max(0, Request::int('kilometrage')),
            'cout_ht'              => $ht,
            'cout_ttc'             => $ttc > 0 ? $ttc : round($ht * 1.2, 2),
            'commentaire'          => mb_substr((string) Request::input('commentaire', ''), 0, 2000),
        ];
    }

    public static function create(array $d): int
    {
        return Database::insert('maintenances', $d);
    }

    public static function update(int $id, array $d): int
    {
        return Database::update('maintenances', $id, $d);
    }

    public static function delete(int $id): int
    {
        // Les fichiers partent avec la prestation : une clé étrangère en CASCADE
        // efface les lignes, mais pas les octets sur le disque. Sans ce retrait,
        // chaque prestation supprimée laisserait derrière elle des factures et
        // des photos que rien ne rattacherait plus à rien.
        foreach (self::files($id) as $fichier) {
            Upload::remove(self::DOSSIER, (string) $fichier['nom_fichier']);
        }

        return Database::delete('maintenances', $id);
    }

    public static function validate(array $d): array
    {
        $erreurs = [];
        if ($d['vehicule_id'] <= 0) {
            $erreurs[] = 'Sélectionnez un véhicule.';
        }
        if ($d['type_intervention_id'] <= 0) {
            $erreurs[] = 'Sélectionnez un type de prestation.';
        }
        if ($d['date_operation'] === '') {
            $erreurs[] = "La date d'intervention est obligatoire.";
        }
        if ($d['cout_ht'] < 0 || $d['cout_ttc'] < 0) {
            $erreurs[] = 'Les montants ne peuvent pas être négatifs.';
        }
        return $erreurs;
    }

    /** Coût cumulé d'entretien sur une période, avec filtre entité optionnel. */
    public static function totalPeriode(string $du, string $au, ?int $entiteId = null): float
    {
        if ($entiteId !== null && $entiteId > 0) {
            return (float) Database::scalar(
                'SELECT COALESCE(SUM(m.cout_ht), 0)
                 FROM maintenances m
                 INNER JOIN vehicules v ON v.id = m.vehicule_id
                 WHERE m.date_operation BETWEEN :du AND :au AND v.entite_id = :entite',
                ['du' => $du, 'au' => $au, 'entite' => $entiteId],
                0.0
            );
        }

        return (float) Database::scalar(
            'SELECT COALESCE(SUM(cout_ht), 0) FROM maintenances WHERE date_operation BETWEEN :du AND :au',
            ['du' => $du, 'au' => $au],
            0.0
        );
    }

    // --- Pièces jointes -----------------------------------------------------

    public static function files(int $maintenanceId): array
    {
        try {
            return Database::all(
                'SELECT * FROM maintenances_fichiers WHERE maintenance_id = :m ORDER BY id ASC',
                ['m' => $maintenanceId]
            );
        } catch (\PDOException $e) {
            Logger::error('Lecture des pièces jointes d\'entretien impossible', $e);
            return [];
        }
    }

    public static function findFile(int $id): ?array
    {
        return Database::one('SELECT * FROM maintenances_fichiers WHERE id = :id', ['id' => $id]);
    }

    /**
     * Nombre de pièces jointes par prestation, pour une liste d'identifiants.
     *
     * Une requête, pas une par ligne : la liste de l'historique peut porter
     * plusieurs centaines de prestations, et un sous-requête corrélé dans
     * `search()` serait aussi répétée par l'Agenda, dont la requête porte sur
     * une plage de dates et doit rester légère.
     *
     * @param  int[] $ids
     * @return array<int, int> identifiant => nombre de fichiers
     */
    public static function compterFichiers(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $marques = [];
        $params  = [];
        foreach ($ids as $position => $id) {
            $marques[] = ':m' . $position;
            $params['m' . $position] = $id;
        }

        try {
            $lignes = Database::all(
                'SELECT maintenance_id, COUNT(*) AS n
                   FROM maintenances_fichiers
                  WHERE maintenance_id IN (' . implode(', ', $marques) . ')
                  GROUP BY maintenance_id',
                $params
            );
        } catch (\PDOException $e) {
            Logger::error('Comptage des pièces jointes d\'entretien impossible', $e);
            return [];
        }

        $comptes = array_fill_keys($ids, 0);
        foreach ($lignes as $ligne) {
            $comptes[(int) $ligne['maintenance_id']] = (int) $ligne['n'];
        }

        return $comptes;
    }

    /**
     * Le fichier décrit par cette ligne est-il réellement présent sur le disque ?
     *
     * Une ligne ne porte que des métadonnées : rien n'interdit au fichier
     * d'avoir été supprimé manuellement, ou à la ligne d'avoir été importée sans
     * son fichier. Contrôler avant d'afficher une vignette évite l'image cassée,
     * qui se lirait comme une pièce jointe corrompue alors que la ligne est
     * simplement orpheline.
     */
    public static function fichierPresent(array $fichier): bool
    {
        return Upload::absolutePath(self::DOSSIER, (string) $fichier['nom_fichier']) !== null;
    }

    /** La pièce jointe est-elle une image affichable comme vignette ? */
    public static function aVignette(array $fichier): bool
    {
        return self::estImage($fichier) && self::fichierPresent($fichier);
    }

    public static function estImage(array $fichier): bool
    {
        return isset(self::MIMES_VIGNETTE[(string) $fichier['mime']]);
    }

    public static function addFile(
        int $maintenanceId,
        string $nom,
        string $original,
        string $mime,
        int $taille
    ): int {
        return Database::insert('maintenances_fichiers', [
            'maintenance_id' => $maintenanceId,
            'nom_fichier'    => $nom,
            'nom_original'   => mb_substr($original, 0, 190),
            'mime'           => $mime,
            'taille'         => $taille,
        ]);
    }

    public static function deleteFile(int $fileId): bool
    {
        $fichier = self::findFile($fileId);
        if ($fichier === null) {
            return false;
        }
        Upload::remove(self::DOSSIER, (string) $fichier['nom_fichier']);

        return Database::delete('maintenances_fichiers', $fileId) > 0;
    }
}
