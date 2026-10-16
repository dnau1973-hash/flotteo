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

    public const ASSURANCE_STATUTS = [
        'non_prise_en_charge' => 'Non prise en charge',
        'prise_en_charge'     => 'Prise en charge',
    ];

    public const DOSSIER = 'incidents';

    private static bool $schemaVerifie = false;

    /**
     * Migration automatique du schéma (colonnes entité et assurance).
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaVerifie) {
            return;
        }
        self::$schemaVerifie = true;
        SoftDeleteMigration::run();

        try {
            Database::run("ALTER TABLE `incidents` ADD COLUMN IF NOT EXISTS `entite_id` INT UNSIGNED NULL DEFAULT NULL AFTER `vehicule_id`");
            Database::run("ALTER TABLE `incidents` ADD COLUMN IF NOT EXISTS `entite` VARCHAR(140) NULL DEFAULT NULL AFTER `entite_id`");
            Database::run("ALTER TABLE `incidents` ADD COLUMN IF NOT EXISTS `assurance_statut` ENUM('non_prise_en_charge','prise_en_charge') NULL DEFAULT NULL AFTER `statut`");
            Database::run("ALTER TABLE `incidents` ADD COLUMN IF NOT EXISTS `remboursement_attendu` TINYINT(1) NOT NULL DEFAULT 0 AFTER `assurance_statut`");
            Database::run("ALTER TABLE `incidents` ADD COLUMN IF NOT EXISTS `montant_rembourse` DECIMAL(10,2) NULL DEFAULT NULL AFTER `remboursement_attendu`");
            Database::run("ALTER TABLE `incidents` ADD COLUMN IF NOT EXISTS `montant_franchise` DECIMAL(10,2) NULL DEFAULT NULL AFTER `montant_rembourse`");
            Database::run("ALTER TABLE `incidents` ADD COLUMN IF NOT EXISTS `remboursement_percu` TINYINT(1) NOT NULL DEFAULT 0 AFTER `montant_franchise`");
            Database::run("ALTER TABLE `incidents` ADD INDEX IF NOT EXISTS `idx_incidents_entite` (`entite_id`)");
            Database::run("ALTER TABLE `incidents` ADD INDEX IF NOT EXISTS `idx_incidents_assurance` (`assurance_statut`, `remboursement_attendu`, `remboursement_percu`)");
            Database::run("UPDATE `incidents` i JOIN `vehicules` v ON v.id = i.vehicule_id JOIN `entites` e ON e.id = v.entite_id SET i.entite_id = v.entite_id, i.entite = e.nom WHERE i.entite_id IS NULL");
        } catch (\Throwable $e) {
            Logger::error('Mise à jour schéma incidents impossible', $e);
        }
    }

    /**
     * Types de pièces jointes autorisés pour l'aperçu et le téléversement.
     */
    public const MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

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
        SELECT i.*, v.immatriculation, ma.nom AS marque_nom, mo.nom AS modele_nom,
               COALESCE(e.nom, ve.nom, i.entite) AS entite_nom,
               COALESCE(e.code, ve.code) AS entite_code
        FROM incidents i
        INNER JOIN vehicules v ON v.id = i.vehicule_id
        INNER JOIN modeles mo ON mo.id = v.modele_id
        INNER JOIN marques  ma ON ma.id = mo.marque_id
        LEFT JOIN entites ve ON ve.id = v.entite_id
        LEFT JOIN entites e ON e.id = i.entite_id';

    public static function find(int $id): ?array
    {
        self::ensureSchema();
        $i = Database::one(self::SELECT_BASE . ' WHERE i.id = :id', ['id' => $id]);
        if ($i !== null && $i['deleted_at'] !== null && !\Core\Auth::can(\Core\Auth::ROLE_ADMIN)) {
            return null;
        }
        return $i;
    }

    public static function search(array $filtres = []): array
    {
        self::ensureSchema();
        $where  = [];
        $params = [];

        $isAdmin = \Core\Auth::can(\Core\Auth::ROLE_ADMIN);
        $avecArchives = !empty($filtres['avec_archives']) && (string) $filtres['avec_archives'] === '1';
        if (!$isAdmin || !$avecArchives) {
            $where[] = 'i.deleted_at IS NULL';
        }

        if (!empty($filtres['vehicule_id'])) {
            $where[] = 'i.vehicule_id = :v';
            $params['v'] = (int) $filtres['vehicule_id'];
        }
        if (!empty($filtres['entite'])) {
            $where[] = '(i.entite_id = :entite OR (i.entite_id IS NULL AND v.entite_id = :entite))';
            $params['entite'] = (int) $filtres['entite'];
        }
        if (!empty($filtres['type'])) {
            $where[] = 'i.type = :t';
            $params['t'] = (string) $filtres['type'];
        }
        if (!empty($filtres['statut'])) {
            $where[] = 'i.statut = :s';
            $params['s'] = (string) $filtres['statut'];
        }
        if (!empty($filtres['assurance_statut'])) {
            $where[] = 'i.assurance_statut = :ass_statut';
            $params['ass_statut'] = (string) $filtres['assurance_statut'];
        }
        if (!empty($filtres['attente_remboursement']) && (string) $filtres['attente_remboursement'] === '1') {
            $where[] = "i.assurance_statut = 'prise_en_charge' AND (i.remboursement_attendu = 1 OR (i.montant_rembourse IS NOT NULL AND i.montant_rembourse > 0)) AND i.remboursement_percu = 0";
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
            'vehicule_id'           => Request::int('vehicule_id'),
            'entite'                => Request::int('entite'),
            'type'                  => (string) Request::input('type', ''),
            'statut'                => (string) Request::input('statut', ''),
            'du'                    => Request::date('du'),
            'au'                    => Request::date('au'),
            'assurance_statut'      => (string) Request::input('assurance_statut', ''),
            'attente_remboursement' => (string) Request::input('attente_remboursement', ''),
            'avec_archives'         => (string) Request::input('avec_archives', '0'),
        ];
    }

    public static function buildPayload(): array
    {
        $type   = (string) Request::input('type', 'autre');
        $statut = (string) Request::input('statut', 'ouvert');
        $ass    = (string) Request::input('assurance_statut', '');
        if (!in_array($ass, ['non_prise_en_charge', 'prise_en_charge'], true)) {
            $ass = null;
        }

        $entiteId = Request::int('entite_id');
        if ($entiteId <= 0) {
            $entiteId = Request::int('entite');
        }
        $entiteId = $entiteId > 0 ? $entiteId : null;

        $entiteNom = null;
        if ($entiteId !== null) {
            try {
                $entiteNom = (string) Database::scalar('SELECT nom FROM entites WHERE id = :id', ['id' => $entiteId], null);
            } catch (\Throwable) {
                $entiteNom = null;
            }
        }

        $rembAttendu = Request::int('remboursement_attendu') === 1 ? 1 : 0;
        $rembPercu   = Request::int('remboursement_percu') === 1 ? 1 : 0;

        // Si le remboursement est déclaré comme perçu, le statut d'assurance est obligatoirement pris en charge
        if ($rembPercu === 1 && empty($ass)) {
            $ass = 'prise_en_charge';
        }

        $rawRemb = Request::input('montant_rembourse');
        $montantRemb = ($rawRemb !== null && $rawRemb !== '' && is_numeric(str_replace(',', '.', (string) $rawRemb)))
            ? round((float) str_replace(',', '.', (string) $rawRemb), 2)
            : null;

        $rawFran = Request::input('montant_franchise');
        $montantFran = ($rawFran !== null && $rawFran !== '' && is_numeric(str_replace(',', '.', (string) $rawFran)))
            ? round((float) str_replace(',', '.', (string) $rawFran), 2)
            : null;

        return [
            'vehicule_id'           => Request::int('vehicule_id'),
            'entite_id'             => $entiteId,
            'entite'                => $entiteNom,
            'type'                  => array_key_exists($type, self::TYPES) ? $type : 'autre',
            'date_incident'         => Request::date('date_incident'),
            'lieu'                  => mb_substr((string) Request::input('lieu', ''), 0, 160),
            'responsable'           => Request::int('responsable') === 1 ? 1 : 0,
            'immatricule'           => Request::int('immatricule') === 1 ? 1 : 0,
            'statut'                => array_key_exists($statut, self::STATUTS) ? $statut : 'ouvert',
            'assurance_statut'      => $ass,
            'remboursement_attendu' => ($ass === 'prise_en_charge') ? $rembAttendu : 0,
            'montant_rembourse'     => ($ass === 'prise_en_charge') ? $montantRemb : null,
            'montant_franchise'     => ($ass === 'prise_en_charge') ? $montantFran : null,
            'remboursement_percu'   => $rembPercu,
            'description'           => mb_substr((string) Request::input('description', ''), 0, 4000),
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

        $rembPercu = !empty($d['remboursement_percu']);
        $rembAttendu = !empty($d['remboursement_attendu']) || (($d['assurance_statut'] ?? '') === 'prise_en_charge' && (!empty($d['montant_rembourse']) && (float) $d['montant_rembourse'] > 0));
        $estCloture = ($d['statut'] ?? '') === 'cloture' || ($d['statut'] ?? '') === 'clos' || ($d['statut'] ?? '') === 'resolu';

        // Contrôle de saisie obligatoire du montant perçu de l'assurance si le remboursement est indiqué perçu
        if ($rembPercu && ($d['montant_rembourse'] === null || (float) $d['montant_rembourse'] <= 0)) {
            $erreurs[] = "Le montant perçu de l'assurance doit être obligatoirement renseigné (montant supérieur à 0) lorsque le remboursement est déclaré comme perçu.";
        }

        // Règle métier : interdiction de clôturer l'incident si un remboursement est attendu mais pas perçu
        if ($estCloture && $rembAttendu && !$rembPercu) {
            $erreurs[] = "Impossible de clôturer l'incident : un remboursement d'assurance est attendu et n'a pas encore été perçu.";
        }

        // Contrôles des montants
        if ($d['montant_rembourse'] !== null && (float) $d['montant_rembourse'] < 0) {
            $erreurs[] = "Le montant perçu de l'assurance ne peut pas être négatif.";
        }
        if ($d['montant_franchise'] !== null && (float) $d['montant_franchise'] < 0) {
            $erreurs[] = 'Le montant de la franchise ne peut pas être négatif.';
        }

        return $erreurs;
    }

    public static function create(array $d): int
    {
        self::ensureSchema();
        return Database::insert('incidents', $d);
    }

    public static function update(int $id, array $d): int
    {
        self::ensureSchema();
        return Database::update('incidents', $id, $d);
    }

    /**
     * Suppression logique (soft delete) : masqué pour les non-admins,
     * conserve les photos et rapports de sinistre.
     */
    public static function softDelete(int $id): int
    {
        SoftDeleteMigration::run();
        return Database::update('incidents', $id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Restauration d'un incident archivé (admin).
     */
    public static function restore(int $id): int
    {
        SoftDeleteMigration::run();
        return Database::update('incidents', $id, ['deleted_at' => null]);
    }

    /**
     * Suppression définitive et suppression physique des pièces jointes (admin).
     */
    public static function forceDelete(int $id): int
    {
        SoftDeleteMigration::run();
        foreach (self::files($id) as $f) {
            Upload::remove(self::DOSSIER, (string) $f['nom_fichier']);
        }
        return Database::delete('incidents', $id);
    }

    /** Alias rétro-compatible de softDelete. */
    public static function delete(int $id): int
    {
        return self::softDelete($id);
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
