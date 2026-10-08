<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;
use Core\Request;
use Core\Upload;

/**
 * Incidents, sinistres et pièces jointes associées.
 */
final class Incident
{
    public const TYPES = [
        'accident'    => 'Accident',
        'panne'       => 'Panne mécanique',
        'vandalisme'  => 'Vandalisme',
        'bris_glace'  => 'Bris de glace',
        'autre'       => 'Autre',
    ];

    public const STATUTS = [
        'ouvert'        => 'Ouvert',
        'en_traitement' => 'En traitement',
        'cloture'       => 'Clôturé',
    ];

    public const DOSSIER = 'incidents';

    /**
     * Types de pièces jointes présentables en vignette.
     *
     * Uniquement des images matricielles : ce sont les seules pièces jointes
     * dont le navigateur sait décoder le contenu sans octet de signature. Le
     * PDF, seul autre type autorisé au téléversement, n'a pas de vignette — le
     * format n'en définit pas — et reçoit une icône à sa place.
     */
    public const MIMES_VIGNETTE = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Une pièce jointe peut-elle être affichée en vignette ? */
    public static function estImage(array $fichier): bool
    {
        return isset(self::MIMES_VIGNETTE[(string) $fichier['mime']]);
    }

    public const SELECT_BASE = '
        SELECT i.*, v.immatriculation, ma.nom AS marque_nom, mo.nom AS modele_nom
        FROM incidents i
        INNER JOIN vehicules v ON v.id = i.vehicule_id
        INNER JOIN modeles mo ON mo.id = v.modele_id
        INNER JOIN marques  ma ON ma.id = mo.marque_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT_BASE . ' WHERE i.id = :id', ['id' => $id]);
    }

    public static function search(array $filtres = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filtres['vehicule_id'])) {
            $where[] = 'i.vehicule_id = :v';
            $params['v'] = (int) $filtres['vehicule_id'];
        }
        if (!empty($filtres['type'])) {
            $where[] = 'i.type = :t';
            $params['t'] = (string) $filtres['type'];
        }
        if (!empty($filtres['statut'])) {
            $where[] = 'i.statut = :s';
            $params['s'] = (string) $filtres['statut'];
        }
        if (!empty($filtres['du'])) {
            $where[] = 'i.date_incident >= :du';
            $params['du'] = (string) $filtres['du'];
        }
        if (!empty($filtres['au'])) {
            $where[] = 'i.date_incident <= :au';
            $params['au'] = (string) $filtres['au'];
        }

        $sql = self::SELECT_BASE;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        try {
            return Database::all($sql . ' ORDER BY i.date_incident DESC, i.id DESC', $params);
        } catch (\PDOException $e) {
            Logger::error('Lecture des incidents impossible', $e);
            return [];
        }
    }

    public static function filtersFromRequest(): array
    {
        return [
            'vehicule_id' => Request::int('vehicule_id'),
            'type'        => (string) Request::input('type', ''),
            'statut'      => (string) Request::input('statut', ''),
            'du'          => Request::date('du'),
            'au'          => Request::date('au'),
        ];
    }

    public static function buildPayload(): array
    {
        $type   = (string) Request::input('type', 'autre');
        $statut = (string) Request::input('statut', 'ouvert');
        return [
            'vehicule_id'   => Request::int('vehicule_id'),
            'type'          => array_key_exists($type, self::TYPES) ? $type : 'autre',
            'date_incident' => Request::date('date_incident'),
            'lieu'          => mb_substr((string) Request::input('lieu', ''), 0, 160),
            'responsable'   => Request::int('responsable') === 1 ? 1 : 0,
            'immatricule'   => Request::int('immatricule') === 1 ? 1 : 0,
            'statut'        => array_key_exists($statut, self::STATUTS) ? $statut : 'ouvert',
            'description'   => mb_substr((string) Request::input('description', ''), 0, 4000),
        ];
    }

    public static function validate(array $d): array
    {
        $erreurs = [];
        if ($d['vehicule_id'] <= 0) {
            $erreurs[] = 'Sélectionnez un véhicule.';
        }
        if ($d['date_incident'] === '') {
            $erreurs[] = "La date de l'incident est obligatoire.";
        }
        return $erreurs;
    }

    public static function create(array $d): int
    {
        return Database::insert('incidents', $d);
    }

    public static function update(int $id, array $d): int
    {
        return Database::update('incidents', $id, $d);
    }

    public static function delete(int $id): int
    {
        foreach (self::files($id) as $f) {
            Upload::remove(self::DOSSIER, (string) $f['nom_fichier']);
        }
        return Database::delete('incidents', $id);
    }

    // --- Pièces jointes -----------------------------------------------------

    public static function files(int $incidentId): array
    {
        try {
            return Database::all(
                'SELECT * FROM incidents_fichiers WHERE incident_id = :i ORDER BY id ASC',
                ['i' => $incidentId]
            );
        } catch (\PDOException $e) {
            Logger::error('Lecture des pièces jointes impossible', $e);
            return [];
        }
    }

    public static function findFile(int $id): ?array
    {
        return Database::one('SELECT * FROM incidents_fichiers WHERE id = :id', ['id' => $id]);
    }

    /**
     * Le fichier décrit par cette ligne est-il réellement présent sur le disque ?
     *
     * La ligne ne porte que des métadonnées : le jeu de démonstration enregistre
     * ainsi ses quatorze pièces jointes *sans* les fichiers correspondants (voir
     * `sql/demodata.sql`), et rien n'interdit à une ligne de survivre à la
     * suppression manuelle d'un fichier. Le contrôle est donc nécessaire avant
     * d'afficher une vignette : une `<img>` pointant sur un fichier absent
     * s'affiche en image cassée, ce qui se lit comme une pièce jointe corrompue
     * alors que la ligne est simplement orpheline.
     */
    public static function fichierPresent(array $fichier): bool
    {
        return Upload::absolutePath(self::DOSSIER, (string) $fichier['nom_fichier']) !== null;
    }

    /** La pièce jointe a-t-elle une vignette à afficher ? */
    public static function aVignette(array $fichier): bool
    {
        return self::estImage($fichier) && self::fichierPresent($fichier);
    }

    public static function addFile(int $incidentId, string $nom, string $original, string $mime, int $taille): int
    {
        return Database::insert('incidents_fichiers', [
            'incident_id'  => $incidentId,
            'nom_fichier'  => $nom,
            'nom_original' => mb_substr($original, 0, 190),
            'mime'         => $mime,
            'taille'       => $taille,
        ]);
    }

    public static function deleteFile(int $fileId): bool
    {
        $fichier = self::findFile($fileId);
        if ($fichier === null) {
            return false;
        }
        Upload::remove(self::DOSSIER, (string) $fichier['nom_fichier']);
        return Database::delete('incidents_fichiers', $fileId) > 0;
    }
}
