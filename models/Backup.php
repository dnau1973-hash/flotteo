<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;

/**
 * Sauvegardes : réglages du module et historique des archives produites.
 *
 * Deux responsabilités distinctes, volontairement reunited ici :
 *  - les réglages vivent dans `parametres`, comme tous les autres paramètres de
 *    l'application ; ils sont lus et écrits par clé, jamais en bloc ;
 *  - l'historique vit dans `sauvegardes`, une ligne par archive, et porte le
 *    verdict d'externalisation Samba en plus des métadonnées du fichier.
 *
 * Aucun chemin de stockage n'est stocké en base : `fichier` ne contient que le
 * nom, le répertoire est décidé par `Services\BackupService`. Un nomissu d'une
 * saisie ne peut donc pas désigner un emplacement arbitraire.
 */
final class Backup
{
    /**
     * Clés de paramétrage du module, avec leur valeur par défaut.
     *
     * @var array<string, string>
     */
    public const REGLAGES = [
        'sauvegarde_retention_jours' => '30',
        'sauvegarde_partage_actif'    => '0',
        'samba_hote'                 => '',
        'samba_partage'              => '',
        'samba_repertoire'           => '',
        'samba_utilisateur'          => '',
        'samba_mot_de_passe'         => '',
        'samba_domaine'              => '',
    ];

    /** Verdict d'externalisation, dans l'ordre d'affichage. */
    public const SAMBA_STATUTS = [
        'non_configure' => 'Non configuré',
        'reussi'        => 'Copié',
        'echec'         => 'Échec',
    ];

    /** Durée de conservation minimale et maximale, en jours. */
    public const RETENTION_MIN = 1;
    public const RETENTION_MAX = 3650;

    // ------------------------------------------------------------------ réglages

    /** Tous les réglages, valeurs par défaut complétées. @return array<string, string> */
    public static function reglages(): array
    {
        $valeurs = self::REGLAGES;
        foreach (Database::all('SELECT cle, valeur FROM parametres') as $ligne) {
            $cle = (string) $ligne['cle'];
            if (array_key_exists($cle, $valeurs)) {
                $valeurs[$cle] = (string) $ligne['valeur'];
            }
        }

        return $valeurs;
    }

    /**
     * Valeur d'un réglage, la valeur par défaut servant de repli.
     *
     * La lecture ne filtre pas sur les clés connues : la méthode est aussi
     * employée pour des clés ponctuelles. Le repli reste systématique, une base
     * antérieure à ce module n'ayant aucune des lignes ci-dessus.
     */
    public static function reglage(string $cle, string $defaut = ''): string
    {
        $valeur = Database::scalar(
            'SELECT valeur FROM parametres WHERE cle = :c',
            ['c' => $cle]
        );

        return $valeur === null ? $defaut : (string) $valeur;
    }

    /** Écrit un réglage, sans écraser une valeur existante. */
    public static function ecrireReglage(string $cle, string $valeur): void
    {
        Database::run(
            'INSERT INTO parametres (cle, valeur) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valeur = :v2',
            ['c' => $cle, 'v' => mb_substr($valeur, 0, 190), 'v2' => mb_substr($valeur, 0, 190)]
        );
    }

    /** Nombre de jours de conservation retenu, borné. */
    public static function retentionJours(): int
    {
        $valeur = (int) self::reglage('sauvegarde_retention_jours', (string) self::REGLAGES['sauvegarde_retention_jours']);

        return max(self::RETENTION_MIN, min(self::RETENTION_MAX, $valeur));
    }

    // ---------------------------------------------------------------- historique

    /** S'assure que la table sauvegardes existe en base de données. */
    public static function assurerTable(): void
    {
        try {
            Database::run('CREATE TABLE IF NOT EXISTS sauvegardes (
                id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                fichier           VARCHAR(190) NOT NULL,
                taille            INT UNSIGNED NOT NULL DEFAULT 0,
                empreinte         CHAR(64) NULL,
                tables_dump       TINYINT UNSIGNED NOT NULL DEFAULT 0,
                fichiers_inclus   INT UNSIGNED NOT NULL DEFAULT 0,
                samba_statut      ENUM(\'non_configure\',\'reussi\',\'echec\') NOT NULL DEFAULT \'non_configure\',
                samba_message     VARCHAR(255) NULL,
                samba_at          DATETIME NULL,
                created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_sauvegardes_fichier (fichier),
                KEY idx_sauvegardes_date (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (\PDOException $e) {
            Logger::error('Création de la table sauvegardes impossible', $e);
        }
    }

    /**
     * Historique des archives, la plus récente d'abord.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function historique(int $limite = 100): array
    {
        self::assurerTable();
        try {
            return Database::all(
                'SELECT * FROM sauvegardes ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limite)
            );
        } catch (\PDOException $e) {
            Logger::error('Lecture de l\'historique des sauvegardes impossible', $e);
            return [];
        }
    }

    /** Une sauvegarde par son identifiant, ou null. */
    public static function find(int $id): ?array
    {
        self::assurerTable();
        try {
            return Database::one('SELECT * FROM sauvegardes WHERE id = :id', ['id' => $id]);
        } catch (\PDOException $e) {
            Logger::error('Lecture d\'une sauvegarde impossible', $e);
            return null;
        }
    }

    /**
     * Enregistre une archive et retourne son identifiant.
     *
     * @param array{fichier: string, taille: int, empreinte: string, tables_dump: int, fichiers_inclus: int, samba_statut: string, samba_message: ?string, created_at?: ?string} $donnees
     */
    public static function enregistrer(array $donnees): int
    {
        self::assurerTable();
        $champs = [
            'fichier'         => $donnees['fichier'],
            'taille'          => $donnees['taille'],
            'empreinte'       => $donnees['empreinte'],
            'tables_dump'     => $donnees['tables_dump'],
            'fichiers_inclus' => $donnees['fichiers_inclus'],
            'samba_statut'    => $donnees['samba_statut'],
            'samba_message'   => $donnees['samba_message'],
            'samba_at'        => $donnees['samba_statut'] === 'non_configure' ? null : date('Y-m-d H:i:s'),
        ];
        if (!empty($donnees['created_at'])) {
            $champs['created_at'] = $donnees['created_at'];
        }

        return Database::insert('sauvegardes', $champs);
    }

    /** Complète le verdict d'externalisation d'une archive. */
    public static function majSamba(int $id, string $statut, ?string $message): void
    {
        Database::update('sauvegardes', $id, [
            'samba_statut'  => $statut,
            'samba_message' => $message === null ? null : mb_substr($message, 0, 255),
            'samba_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /** Supprime une ligne d'historique. */
    public static function supprimer(int $id): int
    {
        return Database::delete('sauvegardes', $id);
    }

    /** Nombre d'archives enregistrées. */
    public static function compter(): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM sauvegardes', [], 0);
    }
}