<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;
use Core\Request;

/**
 * Accès au parc de véhicules et à ses jointures de nomenclature.
 */
final class Vehicle
{
    public const STATUTS = [
        'actif'       => 'Actif',
        'immobilise'  => 'Immobilisé',
        'sorti'       => 'Sorti de flotte',
    ];

    public const SELECT_BASE = '
        SELECT v.*,
               mo.nom AS modele_nom, ma.nom AS marque_nom,
               e.nom AS entite_nom, e.code AS entite_code,
               l.nom AS loueur_nom, li.nom AS lieu_nom, li.ville AS lieu_ville
        FROM vehicules v
        INNER JOIN modeles mo ON mo.id = v.modele_id
        INNER JOIN marques  ma ON ma.id = mo.marque_id
        INNER JOIN entites  e  ON e.id  = v.entite_id
        INNER JOIN loueurs  l  ON l.id  = v.loueur_id
        INNER JOIN lieux    li ON li.id = v.lieu_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT_BASE . ' WHERE v.id = :id', ['id' => $id]);
    }

    /**
     * Liste filtrable du parc.
     *
     * @param array{q?:string, entite?:int, loueur?:int, lieu?:int, statut?:string, echeance?:string} $filtres
     */
    public static function search(array $filtres = []): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($filtres['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(v.immatriculation LIKE :q OR mo.nom LIKE :q OR ma.nom LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        foreach (['entite' => 'v.entite_id', 'loueur' => 'v.loueur_id', 'lieu' => 'v.lieu_id'] as $cle => $col) {
            if (!empty($filtres[$cle])) {
                $where[] = "$col = :$cle";
                $params[$cle] = (int) $filtres[$cle];
            }
        }
        if (!empty($filtres['statut'])) {
            $where[] = 'v.statut = :statut';
            $params['statut'] = (string) $filtres['statut'];
        }
        if (($filtres['echeance'] ?? '') === '90') {
            $where[] = 'v.date_sortie_effective IS NULL AND v.date_sortie_prevue <= :echeance';
            $params['echeance'] = date('Y-m-d', strtotime('+90 days'));
        }

        $sql = self::SELECT_BASE;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        try {
            return Database::all($sql . ' ORDER BY v.date_sortie_prevue ASC', $params);
        } catch (\PDOException $e) {
            Logger::error('Lecture du parc impossible', $e);
            return [];
        }
    }

    /** Filtres extraits de la requête courante. */
    public static function filtersFromRequest(): array
    {
        return [
            'q'        => (string) Request::input('q', ''),
            'entite'   => Request::int('entite'),
            'loueur'   => Request::int('loueur'),
            'lieu'     => Request::int('lieu'),
            'statut'   => (string) Request::input('statut', ''),
            'echeance' => (string) Request::input('echeance', ''),
        ];
    }

    /** Construit un jeu de données validé à partir du formulaire. */
    public static function buildPayload(): array
    {
        $sortiePrevue = Request::date('date_sortie_prevue');
        $statut       = (string) Request::input('statut', 'actif');

        return [
            'immatriculation'       => strtoupper((string) Request::input('immatriculation', '')),
            'modele_id'             => Request::int('modele_id'),
            'entite_id'             => Request::int('entite_id'),
            'loueur_id'             => Request::int('loueur_id'),
            'lieu_id'               => Request::int('lieu_id'),
            'date_entree'           => Request::date('date_entree'),
            'date_sortie_prevue'    => $sortiePrevue,
            'date_sortie_effective' => Request::date('date_sortie_effective'),
            'statut'                => array_key_exists($statut, self::STATUTS) ? $statut : 'actif',
            'immatricule'           => Request::int('immatricule') === 1 ? 1 : 0,
            'commentaire'           => mb_substr((string) Request::input('commentaire', ''), 0, 2000),
        ];
    }

    public static function create(array $data): int
    {
        return Database::insert('vehicules', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::update('vehicules', $id, $data);
    }

    public static function delete(int $id): int
    {
        return Database::delete('vehicules', $id);
    }

    public static function immatriculationExists(string $immat, ?int $horsId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM vehicules WHERE immatriculation = :i';
        $params = ['i' => $immat];
        if ($horsId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $horsId;
        }
        return (int) Database::scalar($sql, $params, 0) > 0;
    }

    /** Règles de validation métier, sous forme de liste de messages. */
    public static function validate(array $d): array
    {
        $erreurs = [];
        if ($d['immatriculation'] === '') {
            $erreurs[] = "L'immatriculation est obligatoire.";
        }
        foreach (['modele_id', 'entite_id', 'loueur_id', 'lieu_id'] as $cle) {
            if ($d[$cle] <= 0) {
                $erreurs[] = 'Toutes les rubriques de nomenclature sont obligatoires.';
                break;
            }
        }
        if ($d['date_entree'] === '') {
            $erreurs[] = "La date d'entrée en flotte est obligatoire.";
        }
        if ($d['date_sortie_prevue'] === '') {
            $erreurs[] = 'La date de sortie prévisionnelle est obligatoire.';
        }
        if ($d['date_entree'] !== '' && $d['date_sortie_prevue'] !== ''
            && $d['date_sortie_prevue'] < $d['date_entree']) {
            $erreurs[] = 'La date de sortie doit être postérieure à la date d\'entrée.';
        }
        return array_values(array_unique($erreurs));
    }

    /** Jours restants avant restitution (null si véhicule déjà sorti). */
    public static function joursRestants(array $vehicule): ?int
    {
        if (!empty($vehicule['date_sortie_effective'])) {
            return null;
        }
        $diff = strtotime((string) $vehicule['date_sortie_prevue']) - strtotime(date('Y-m-d'));
        return (int) round($diff / 86400);
    }

    /** Statistiques de parc pour les cartes KPI. */
    public static function stats(): array
    {
        $row = Database::one('
            SELECT
                COUNT(*) AS total,
                SUM(statut = \'actif\')      AS actifs,
                SUM(statut = \'immobilise\') AS immobilises,
                SUM(statut = \'sorti\')      AS sortis
            FROM vehicules') ?? [];

        $alertes = (int) Database::scalar('
            SELECT COUNT(*) FROM vehicules
            WHERE date_sortie_effective IS NULL AND date_sortie_prevue <= :limite',
            ['limite' => date('Y-m-d', strtotime('+90 days'))], 0);

        $total = (int) ($row['total'] ?? 0);

        return [
            'total'        => $total,
            'actifs'       => (int) ($row['actifs'] ?? 0),
            'immobilises'  => (int) ($row['immobilises'] ?? 0),
            'sortis'       => (int) ($row['sortis'] ?? 0),
            'taux_immobilisation' => $total > 0 ? round((int) ($row['immobilises'] ?? 0) * 100 / $total, 1) : 0.0,
            'echeances_90j' => $alertes,
        ];
    }
}
