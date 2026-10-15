<?php
declare(strict_types=1);

namespace Models;

use Core\Auth;
use Core\Database;
use Core\Logger;
use Core\Request;

/**
 * Journal d'audit et de traçabilité des opérations applicatives.
 *
 * Enregistre les insertions, modifications, suppressions et imports effectués
 * par les utilisateurs (ou le système) sur les différentes entités du parc,
 * avec horodatage, auteur, véhicule associé (immatriculation), description
 * lisible et données avant/après.
 */
final class AuditLog
{
    public const ACTION_CREATION     = 'creation';
    public const ACTION_MODIFICATION = 'modification';
    public const ACTION_SUPPRESSION  = 'suppression';
    public const ACTION_IMPORT       = 'import';

    public const ACTIONS = [
        self::ACTION_CREATION     => 'Création',
        self::ACTION_MODIFICATION => 'Modification',
        self::ACTION_SUPPRESSION  => 'Suppression',
        self::ACTION_IMPORT       => 'Import',
    ];

    public const ACTIONS_BADGES = [
        self::ACTION_CREATION     => 'bg-green-lt text-green',
        self::ACTION_MODIFICATION => 'bg-blue-lt text-blue',
        self::ACTION_SUPPRESSION  => 'bg-red-lt text-red',
        self::ACTION_IMPORT       => 'bg-purple-lt text-purple',
    ];

    public const ACTIONS_ICONES = [
        self::ACTION_CREATION     => 'plus',
        self::ACTION_MODIFICATION => 'pen',
        self::ACTION_SUPPRESSION  => 'trash-can',
        self::ACTION_IMPORT       => 'file-arrow-up',
    ];

    public const ENTITES = [
        'vehicule'    => 'Véhicules',
        'entretien'   => 'Révisions & Entretien',
        'incident'    => 'Incidents & Sinistres',
        'kilometrage' => 'Kilométrage',
        'document'    => 'Documents & Justificatifs',
        'utilisateur' => 'Utilisateurs',
        'dictionnaire'=> 'Référentiels',
        'parametre'   => 'Paramètres système',
        'sauvegarde'  => 'Sauvegardes',
    ];

    public const ENTITES_BADGES = [
        'vehicule'    => 'bg-primary-lt text-primary',
        'entretien'   => 'bg-azure-lt text-azure',
        'incident'    => 'bg-orange-lt text-orange',
        'kilometrage' => 'bg-indigo-lt text-indigo',
        'document'    => 'bg-teal-lt text-teal',
        'utilisateur' => 'bg-yellow-lt text-yellow',
        'dictionnaire'=> 'bg-secondary-lt text-secondary',
        'parametre'   => 'bg-dark-lt text-dark',
        'sauvegarde'  => 'bg-purple-lt text-purple',
    ];

    private static bool $tableVerifiee = false;

    /**
     * Crée automatiquement la table si elle n'existe pas encore.
     */
    public static function ensureTable(): void
    {
        if (self::$tableVerifiee) {
            return;
        }
        self::$tableVerifiee = true;

        SoftDeleteMigration::run();

        try {
            Database::run("
                CREATE TABLE IF NOT EXISTS journal_audit (
                    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    utilisateur_id  INT UNSIGNED NULL DEFAULT NULL,
                    utilisateur_nom VARCHAR(120) NULL DEFAULT NULL,
                    action          ENUM('creation', 'modification', 'suppression', 'import') NOT NULL,
                    entite          VARCHAR(40) NOT NULL,
                    entite_id       INT UNSIGNED NULL DEFAULT NULL,
                    immatriculation VARCHAR(20) NULL DEFAULT NULL,
                    description     VARCHAR(255) NOT NULL,
                    details         TEXT NULL DEFAULT NULL,
                    ip              VARCHAR(45) NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_journal_created_at (created_at),
                    KEY idx_journal_action (action),
                    KEY idx_journal_entite (entite),
                    KEY idx_journal_immat (immatriculation),
                    KEY idx_journal_user (utilisateur_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable $e) {
            Logger::error('Initialisation table journal_audit impossible', $e);
        }
    }

    /**
     * Enregistre un événement dans le journal d'audit.
     */
    public static function log(
        string $action,
        string $entite,
        ?int $entiteId = null,
        ?string $immatriculation = null,
        string $description = '',
        array|string|null $details = null
    ): void {
        try {
            self::ensureTable();

            $user = Auth::user();
            $userId = Auth::id();
            $userNom = $user['nom'] ?? ($userId ? "Utilisateur #$userId" : 'Système');

            $immat = null;
            if ($immatriculation !== null && trim($immatriculation) !== '') {
                $immat = mb_strtoupper(trim($immatriculation));
            }

            $detailsJson = null;
            if (is_array($details)) {
                $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_string($details)) {
                $detailsJson = $details;
            }

            $ip = Request::ip();

            Database::insert('journal_audit', [
                'utilisateur_id'  => $userId,
                'utilisateur_nom' => $userNom,
                'action'          => $action,
                'entite'          => $entite,
                'entite_id'       => $entiteId,
                'immatriculation' => $immat,
                'description'     => mb_substr($description, 0, 255),
                'details'         => $detailsJson,
                'ip'              => $ip !== '' ? mb_substr($ip, 0, 45) : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Échec écriture journal d\'audit: ' . $e->getMessage(), $e);
        }
    }

    /** Enregistrement d'une insertion / création. */
    public static function creation(
        string $entite,
        ?int $entiteId,
        ?string $immatriculation,
        string $description,
        array|string|null $details = null
    ): void {
        self::log(self::ACTION_CREATION, $entite, $entiteId, $immatriculation, $description, $details);
    }

    /** Enregistrement d'une modification. */
    public static function modification(
        string $entite,
        ?int $entiteId,
        ?string $immatriculation,
        string $description,
        array|string|null $details = null
    ): void {
        self::log(self::ACTION_MODIFICATION, $entite, $entiteId, $immatriculation, $description, $details);
    }

    /** Enregistrement d'une suppression. */
    public static function suppression(
        string $entite,
        ?int $entiteId,
        ?string $immatriculation,
        string $description,
        array|string|null $details = null
    ): void {
        self::log(self::ACTION_SUPPRESSION, $entite, $entiteId, $immatriculation, $description, $details);
    }

    /**
     * Calcule la différence entre deux versions d'un enregistrement.
     *
     * @return array{avant: array<string, mixed>, apres: array<string, mixed>, message?: string, aucun_changement?: bool}
     */
    public static function diff(array $avant, array $apres, array $champsIgnores = ['updated_at', 'created_at', 'password_hash']): array
    {
        $diffAvant = [];
        $diffApres = [];

        $normaliser = static function (mixed $val): ?string {
            if ($val === null) {
                return null;
            }
            $str = trim((string) $val);
            return $str === '' ? null : $str;
        };

        foreach ($apres as $cle => $valeurApres) {
            if (in_array($cle, $champsIgnores, true)) {
                continue;
            }
            $valeurAvant = $avant[$cle] ?? null;
            $normAvant   = $normaliser($valeurAvant);
            $normApres   = $normaliser($valeurApres);

            if ($normAvant !== $normApres) {
                $diffAvant[$cle] = $valeurAvant;
                $diffApres[$cle] = $valeurApres;
            }
        }

        if ($diffAvant === [] && $diffApres === []) {
            return [
                'avant'            => [],
                'apres'            => [],
                'aucun_changement' => true,
                'message'          => 'Aucune modification : les valeurs sont identiques à la version existante.',
            ];
        }

        return ['avant' => $diffAvant, 'apres' => $diffApres];
    }

    /**
     * Récupère les filtres de recherche depuis la requête HTTP.
     *
     * @return array{immatriculation: string, date_debut: string, date_fin: string, action: string, entite: string, utilisateur_id: int, q: string}
     */
    public static function filtersFromRequest(): array
    {
        return [
            'immatriculation' => mb_strtoupper(trim((string) Request::input('immatriculation', ''))),
            'date_debut'      => Request::date('date_debut'),
            'date_fin'        => Request::date('date_fin'),
            'action'          => (string) Request::input('action', ''),
            'entite'          => (string) Request::input('entite', ''),
            'utilisateur_id'  => Request::int('utilisateur_id'),
            'q'               => trim((string) Request::input('q', '')),
        ];
    }

    /**
     * Prépare les clauses WHERE et paramètres pour le filtrage du journal.
     */
    private static function buildWhere(array $filtres): array
    {
        $where  = [];
        $params = [];

        // Recherche par immatriculation (partielle ou exacte, insensible aux tirets)
        $immat = trim((string) ($filtres['immatriculation'] ?? ''));
        if ($immat !== '') {
            $immatClean = str_replace(['-', ' ', '.'], '', $immat);
            $where[] = '(j.immatriculation LIKE :immat_raw OR REPLACE(REPLACE(j.immatriculation, "-", ""), " ", "") LIKE :immat_clean)';
            $params['immat_raw']   = '%' . $immat . '%';
            $params['immat_clean'] = '%' . $immatClean . '%';
        }

        // Plage de dates
        $dateDebut = (string) ($filtres['date_debut'] ?? '');
        if ($dateDebut !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) {
            $where[] = 'j.created_at >= :date_debut';
            $params['date_debut'] = $dateDebut . ' 00:00:00';
        }

        $dateFin = (string) ($filtres['date_fin'] ?? '');
        if ($dateFin !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
            $where[] = 'j.created_at <= :date_fin';
            $params['date_fin'] = $dateFin . ' 23:59:59';
        }

        // Action
        $action = (string) ($filtres['action'] ?? '');
        if ($action !== '' && isset(self::ACTIONS[$action])) {
            $where[] = 'j.action = :action';
            $params['action'] = $action;
        }

        // Entité
        $entite = (string) ($filtres['entite'] ?? '');
        if ($entite !== '' && isset(self::ENTITES[$entite])) {
            $where[] = 'j.entite = :entite';
            $params['entite'] = $entite;
        }

        // Utilisateur
        $userId = (int) ($filtres['utilisateur_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'j.utilisateur_id = :user_id';
            $params['user_id'] = $userId;
        }

        // Recherche textuelle libre (description ou détails)
        $q = trim((string) ($filtres['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(j.description LIKE :q OR j.details LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        return [$where, $params];
    }

    /**
     * Compte le nombre d'entrées correspondant aux filtres.
     */
    public static function count(array $filtres): int
    {
        self::ensureTable();
        [$where, $params] = self::buildWhere($filtres);
        $clause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        return (int) Database::scalar("SELECT COUNT(*) FROM journal_audit j $clause", $params, 0);
    }

    /**
     * Récupère une page d'entrées correspondant aux filtres.
     */
    public static function search(array $filtres, int $limite = 25, int $offset = 0): array
    {
        self::ensureTable();
        [$where, $params] = self::buildWhere($filtres);
        $clause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT j.*, u.nom AS auteur_actuel_nom, u.email AS auteur_actuel_email
                FROM journal_audit j
                LEFT JOIN utilisateurs u ON u.id = j.utilisateur_id
                $clause
                ORDER BY j.created_at DESC, j.id DESC
                LIMIT " . (int) $limite . " OFFSET " . (int) $offset;

        return Database::all($sql, $params);
    }

    /**
     * Immatriculations présentes dans le journal, pour l'autocomplétion du filtre.
     *
     * La source est le journal lui-même (et non la table des véhicules) : un
     * véhicule supprimé définitivement doit rester recherchable dans l'historique.
     * La marque et le modèle sont ajoutés lorsque le véhicule existe encore.
     *
     * @return list<array{immatriculation: string, libelle: string}>
     */
    public static function immatriculations(): array
    {
        self::ensureTable();

        $lignes = Database::all(
            "SELECT j.immatriculation,
                    MAX(CONCAT_WS(' ', ma.nom, mo.nom)) AS vehicule
             FROM journal_audit j
             LEFT JOIN vehicules v ON UPPER(v.immatriculation) = j.immatriculation
             LEFT JOIN modeles mo ON mo.id = v.modele_id
             LEFT JOIN marques ma ON ma.id = mo.marque_id
             WHERE j.immatriculation IS NOT NULL AND j.immatriculation <> ''
             GROUP BY j.immatriculation
             ORDER BY j.immatriculation"
        );

        return array_map(static function (array $l): array {
            $immat = (string) $l['immatriculation'];
            $vehicule = trim((string) ($l['vehicule'] ?? ''));
            return [
                'immatriculation' => $immat,
                'libelle'         => $vehicule !== '' ? $immat . ' — ' . $vehicule : $immat,
            ];
        }, $lignes);
    }

    /**
     * Récupère un enregistrement précis par son identifiant.
     */
    public static function find(int $id): ?array
    {
        self::ensureTable();
        return Database::one('SELECT * FROM journal_audit WHERE id = :id', ['id' => $id]);
    }

    /**
     * Purge manuelle des anciens logs au-delà d'un nombre de jours.
     *
     * IMPORTANT : La purge du journal d'audit ne peut pas et ne doit jamais être
     * automatique. Aucune tâche cron ni automatisation ne doit appeler cette méthode.
     * La traçabilité doit rester permanente sauf action manuelle d'un administrateur.
     */
    public static function purge(int $jours = 365): int
    {
        self::ensureTable();
        $dateLimite = date('Y-m-d 00:00:00', strtotime("-$jours days"));
        return Database::run('DELETE FROM journal_audit WHERE created_at < :limite', ['limite' => $dateLimite])->rowCount();
    }
}

