<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;
use Core\Upload;

/**
 * Pièces et documents attachés à un véhicule (carte grise, assurance, contrôle technique, contrat, etc.).
 */
final class VehicleDocument
{
    public const DOSSIER = 'vehicules';

    public const TYPE_CARTE_GRISE        = 'carte_grise';
    public const TYPE_ASSURANCE          = 'assurance';
    public const TYPE_CONTROLE_TECHNIQUE = 'controle_technique';
    public const TYPE_CONTRAT            = 'contrat';
    public const TYPE_AUTRE              = 'autre';

    public const TYPES = [
        self::TYPE_CARTE_GRISE        => 'Carte grise',
        self::TYPE_ASSURANCE          => 'Assurance',
        self::TYPE_CONTROLE_TECHNIQUE => 'Contrôle technique',
        self::TYPE_CONTRAT            => 'Contrat / Bon de commande',
        self::TYPE_AUTRE              => 'Autre document',
    ];

    public const TYPES_BADGES = [
        self::TYPE_CARTE_GRISE        => 'bg-purple-lt text-purple',
        self::TYPE_ASSURANCE          => 'bg-green-lt text-green',
        self::TYPE_CONTROLE_TECHNIQUE => 'bg-azure-lt text-azure',
        self::TYPE_CONTRAT            => 'bg-blue-lt text-blue',
        self::TYPE_AUTRE              => 'bg-secondary-lt text-secondary',
    ];

    public const TYPES_ICONES = [
        self::TYPE_CARTE_GRISE        => 'tag',
        self::TYPE_ASSURANCE          => 'shield-halved',
        self::TYPE_CONTROLE_TECHNIQUE => 'wrench',
        self::TYPE_CONTRAT            => 'book',
        self::TYPE_AUTRE              => 'file-lines',
    ];

    public const MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    public const MIMES_VIGNETTE = [
        'image/jpeg' => true,
        'image/png'  => true,
        'image/webp' => true,
    ];

    private static bool $tableInitialisee = false;

    /**
     * Initialise la table SQL si elle n'existe pas encore.
     */
    public static function initTable(): void
    {
        if (self::$tableInitialisee) {
            return;
        }

        try {
            Database::run("CREATE TABLE IF NOT EXISTS vehicules_documents (
                id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                vehicule_id     INT UNSIGNED NOT NULL,
                type            VARCHAR(40) NOT NULL DEFAULT 'autre',
                titre           VARCHAR(140) NOT NULL,
                date_expiration DATE NULL DEFAULT NULL,
                nom_fichier     VARCHAR(80) NOT NULL,
                nom_original    VARCHAR(190) NOT NULL,
                mime            VARCHAR(80) NOT NULL,
                taille          INT UNSIGNED NOT NULL DEFAULT 0,
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_documents_vehicule (vehicule_id),
                KEY idx_documents_type (type),
                CONSTRAINT fk_documents_vehicule FOREIGN KEY (vehicule_id) REFERENCES vehicules (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            self::$tableInitialisee = true;
        } catch (\PDOException $e) {
            Logger::error('Initialisation table vehicules_documents impossible', $e);
        }
    }

    /**
     * Récupère tous les documents associés à un véhicule.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forVehicle(int $vehiculeId, bool $inclureArchives = false): array
    {
        self::initTable();
        SoftDeleteMigration::run();
        try {
            $isAdmin = \Core\Auth::can(\Core\Auth::ROLE_ADMIN);
            $whereClause = ($isAdmin && $inclureArchives) ? '' : ' AND deleted_at IS NULL';
            return Database::all(
                "SELECT * FROM vehicules_documents WHERE vehicule_id = :v {$whereClause} ORDER BY created_at DESC, id DESC",
                ['v' => $vehiculeId]
            );
        } catch (\PDOException $e) {
            Logger::error("Lecture des documents du véhicule #$vehiculeId impossible", $e);
            return [];
        }
    }

    /**
     * Recherche un document par son identifiant unique.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        self::initTable();
        SoftDeleteMigration::run();
        $doc = Database::one('SELECT * FROM vehicules_documents WHERE id = :id', ['id' => $id]);
        if ($doc !== null && $doc['deleted_at'] !== null && !\Core\Auth::can(\Core\Auth::ROLE_ADMIN)) {
            return null;
        }
        return $doc;
    }

    /**
     * Ajoute un document en base.
     */
    public static function add(
        int $vehiculeId,
        string $type,
        string $titre,
        ?string $dateExpiration,
        string $nomFichier,
        string $nomOriginal,
        string $mime,
        int $taille
    ): int {
        self::initTable();

        $libelleType = self::TYPES[$type] ?? self::TYPES[self::TYPE_AUTRE];
        $titreNettoye = trim($titre) !== '' ? trim($titre) : $libelleType;

        return Database::insert('vehicules_documents', [
            'vehicule_id'     => $vehiculeId,
            'type'            => array_key_exists($type, self::TYPES) ? $type : self::TYPE_AUTRE,
            'titre'           => mb_substr($titreNettoye, 0, 140),
            'date_expiration' => !empty($dateExpiration) ? $dateExpiration : null,
            'nom_fichier'     => $nomFichier,
            'nom_original'    => mb_substr($nomOriginal, 0, 190),
            'mime'            => $mime,
            'taille'          => $taille,
        ]);
    }

    /**
     * Suppression logique (soft delete) : rend le document invisible
     * pour les profils non-administrateurs, sans effacer le fichier physique.
     */
    public static function softDelete(int $id): bool
    {
        self::initTable();
        SoftDeleteMigration::run();
        return Database::update('vehicules_documents', $id, ['deleted_at' => date('Y-m-d H:i:s')]) > 0;
    }

    /**
     * Restauration d'un document archivé (admin).
     */
    public static function restore(int $id): bool
    {
        self::initTable();
        SoftDeleteMigration::run();
        return Database::update('vehicules_documents', $id, ['deleted_at' => null]) > 0;
    }

    /**
     * Suppression définitive et suppression physique du fichier (admin).
     */
    public static function forceDelete(int $id): bool
    {
        self::initTable();
        SoftDeleteMigration::run();
        $doc = Database::one('SELECT * FROM vehicules_documents WHERE id = :id', ['id' => $id]);
        if ($doc === null) {
            return false;
        }

        Upload::remove(self::DOSSIER, (string) $doc['nom_fichier']);
        return Database::delete('vehicules_documents', $id) > 0;
    }

    /** Alias rétro-compatible de softDelete. */
    public static function delete(int $id): bool
    {
        return self::softDelete($id);
    }

    /**
     * Indique si le fichier existe physiquement sur le disque.
     */
    public static function fichierPresent(array $doc): bool
    {
        return Upload::absolutePath(self::DOSSIER, (string) ($doc['nom_fichier'] ?? '')) !== null;
    }

    /**
     * Indique si le document est de type image.
     */
    public static function estImage(array $doc): bool
    {
        return isset(self::MIMES_VIGNETTE[(string) ($doc['mime'] ?? '')]);
    }

    /**
     * Indique si une vignette peut être affichée.
     */
    public static function aVignette(array $doc): bool
    {
        return self::estImage($doc) && self::fichierPresent($doc);
    }
}

