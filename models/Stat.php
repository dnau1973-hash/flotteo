<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;

/**
 * Agrégats analytiques du tableau de bord.
 * Chaque méthode renvoie directement une structure exploitable par ApexCharts.
 */
final class Stat
{
    /** Abréviations de mois en français (évite strftime, déprécié en PHP 8.1+). */
    private const MOIS_FR = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    private static function libelleMois(\DateTimeImmutable $date): string
    {
        return self::MOIS_FR[(int) $date->format('n') - 1] . ' ' . $date->format('Y');
    }

    /** Séries mensuelles des sorties de flotte sur 12 mois (échéancier). */
    public static function exitSchedule(int $mois = 12, ?int $entiteId = null): array
    {
        $whereEntite = ($entiteId !== null && $entiteId > 0) ? ' AND entite_id = :entite' : '';
        $params = [
            'du' => date('Y-m-01', strtotime('first day of this month')),
            'au' => date('Y-m-t', strtotime("+$mois months")),
        ];
        if ($entiteId !== null && $entiteId > 0) {
            $params['entite'] = $entiteId;
        }

        try {
            $lignes = Database::all(
                "SELECT DATE_FORMAT(date_sortie_prevue, '%Y-%m') AS mois, COUNT(*) AS total
                 FROM vehicules
                 WHERE date_sortie_effective IS NULL
                   AND date_sortie_prevue BETWEEN :du AND :au{$whereEntite}
                 GROUP BY mois ORDER BY mois ASC",
                $params
            );
        } catch (\PDOException $e) {
            Logger::error('Calcul échéancier impossible', $e);
            return [];
        }

        $series = [];
        $cursor = new \DateTimeImmutable('first day of this month');
        $index  = [];
        foreach ($lignes as $l) {
            $index[(string) $l['mois']] = (int) $l['total'];
        }
        for ($i = 0; $i < $mois; $i++) {
            $cle = $cursor->format('Y-m');
            $series[] = [
                'mois'    => self::libelleMois($cursor),
                'mois_iso' => $cle,
                'total'   => $index[$cle] ?? 0,
            ];
            $cursor = $cursor->modify('+1 month');
        }
        return $series;
    }

    /**
     * Répartition de la flotte par dimension : entite | loueur | lieu | marque | statut.
     *
     * @return array<int, array{label:string, total:int}>
     */
    public static function fleetSplit(string $dimension, string $filtre = '', ?int $entiteId = null): array
    {
        $requetes = [
            'entite' => 'SELECT e.nom AS label, COUNT(*) AS total FROM vehicules v
                         INNER JOIN entites e ON e.id = v.entite_id {where} GROUP BY e.nom ORDER BY total DESC',
            'loueur' => 'SELECT l.nom AS label, COUNT(*) AS total FROM vehicules v
                         INNER JOIN loueurs l ON l.id = v.loueur_id {where} GROUP BY l.nom ORDER BY total DESC',
            'lieu'   => 'SELECT li.nom AS label, COUNT(*) AS total FROM vehicules v
                         INNER JOIN lieux li ON li.id = v.lieu_id {where} GROUP BY li.nom ORDER BY total DESC',
            'marque' => 'SELECT ma.nom AS label, COUNT(*) AS total FROM vehicules v
                         INNER JOIN modeles mo ON mo.id = v.modele_id
                         INNER JOIN marques ma ON ma.id = mo.marque_id {where} GROUP BY ma.nom ORDER BY total DESC',
            'statut' => 'SELECT v.statut AS label, COUNT(*) AS total FROM vehicules v {where} GROUP BY v.statut',
        ];

        $sql = $requetes[$dimension] ?? $requetes['entite'];
        $params = [];
        $whereParts = [];
        if ($filtre !== '' && in_array($dimension, ['entite', 'loueur', 'lieu'], true)) {
            $col = ['entite' => 'v.entite_id', 'loueur' => 'v.loueur_id', 'lieu' => 'v.lieu_id'][$dimension];
            $whereParts[] = "$col = :f";
            $params['f'] = (int) $filtre;
        }
        if ($entiteId !== null && $entiteId > 0) {
            $whereParts[] = 'v.entite_id = :entite';
            $params['entite'] = $entiteId;
        }

        $where = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';

        try {
            $lignes = Database::all(str_replace('{where}', $where, $sql), $params);
        } catch (\PDOException $e) {
            Logger::error("Répartition ($dimension) impossible", $e);
            return [];
        }

        return array_map(
            static fn (array $l): array => [
                'label' => (string) $l['label'],
                'total' => (int) $l['total'],
            ],
            $lignes
        );
    }

    /** Évolution mensuelle des coûts d'entretien, empilée par typologie. */
    public static function maintenanceCosts(int $mois = 12, ?int $entiteId = null): array
    {
        $categories = ['constructeur', 'pneumatique', 'freinage', 'controle', 'carrosserie', 'autre'];
        $whereEntite = ($entiteId !== null && $entiteId > 0) ? ' AND v.entite_id = :entite' : '';
        $params = [
            'du' => date('Y-m-01', strtotime("first day of -" . ($mois - 1) . " months")),
            'au' => date('Y-m-t'),
        ];
        if ($entiteId !== null && $entiteId > 0) {
            $params['entite'] = $entiteId;
        }

        try {
            $lignes = Database::all(
                "SELECT DATE_FORMAT(m.date_operation, '%Y-%m') AS mois, t.categorie,
                        COALESCE(SUM(m.cout_ht), 0) AS total
                 FROM maintenances m
                 INNER JOIN types_intervention t ON t.id = m.type_intervention_id
                 INNER JOIN vehicules v ON v.id = m.vehicule_id
                 WHERE m.date_operation BETWEEN :du AND :au{$whereEntite}
                 GROUP BY mois, t.categorie ORDER BY mois ASC",
                $params
            );
        } catch (\PDOException $e) {
            Logger::error('Calcul des coûts d\'entretien impossible', $e);
            return [];
        }

        $parMois = [];
        foreach ($lignes as $l) {
            $parMois[(string) $l['mois']][(string) $l['categorie']] = (float) $l['total'];
        }

        $etiquettes = [];
        $cursor = new \DateTimeImmutable('first day of -' . ($mois - 1) . ' months');
        for ($i = 0; $i < $mois; $i++) {
            $etiquettes[] = self::libelleMois($cursor);
            $cursor = $cursor->modify('+1 month');
        }

        $series = [];
        foreach ($categories as $cat) {
            $valeurs = [];
            $cursor = new \DateTimeImmutable('first day of -' . ($mois - 1) . ' months');
            for ($i = 0; $i < $mois; $i++) {
                $cle = $cursor->format('Y-m');
                $valeurs[] = round((float) ($parMois[$cle][$cat] ?? 0), 2);
                $cursor = $cursor->modify('+1 month');
            }
            $series[] = ['categorie' => $cat, 'data' => $valeurs];
        }

        return ['etiquettes' => $etiquettes, 'series' => $series];
    }

    /** TCO d'entretien moyen par modèle de véhicule. */
    public static function tcoByModel(int $limite = 12, ?int $entiteId = null): array
    {
        // Plafond borné par une constante entière : aucune saisie utilisateur n'atteint cette clause.
        $plafond = (int) max(1, min(50, $limite));
        $whereEntite = ($entiteId !== null && $entiteId > 0) ? ' AND v.entite_id = :entite' : '';
        $params = [];
        if ($entiteId !== null && $entiteId > 0) {
            $params['entite'] = $entiteId;
        }

        try {
            $lignes = Database::all(
                "SELECT CONCAT(ma.nom, ' ', mo.nom) AS label,
                        COUNT(DISTINCT v.id) AS vehicules,
                        COALESCE(SUM(m.cout_ht), 0) AS total,
                        COALESCE(SUM(m.cout_ht) / NULLIF(COUNT(DISTINCT v.id), 0), 0) AS moyenne
                 FROM maintenances m
                 INNER JOIN vehicules v ON v.id = m.vehicule_id
                 INNER JOIN modeles mo ON mo.id = v.modele_id
                 INNER JOIN marques ma ON ma.id = mo.marque_id
                 WHERE 1=1{$whereEntite}
                 GROUP BY ma.nom, mo.nom
                 HAVING total > 0
                 ORDER BY moyenne DESC
                 LIMIT " . $plafond,
                $params
            );
        } catch (\PDOException $e) {
            Logger::error('Calcul du TCO par modèle impossible', $e);
            return [];
        }

        return array_map(
            static fn (array $l): array => [
                'label'     => (string) $l['label'],
                'vehicules' => (int) $l['vehicules'],
                'total'     => round((float) $l['total'], 2),
                'moyenne'   => round((float) $l['moyenne'], 2),
            ],
            $lignes
        );
    }

    /**
     * Matrice des incidents : répartition par type et tendance mensuelle.
     *
     * @return array{par_type: array<int, array{label:string, total:int}>, tendance: array<int, array{mois:string, total:int}>}
     */
    public static function incidents(int $mois = 12, ?int $entiteId = null): array
    {
        $parType = [];
        $tendance = [];
        $hasEntite = ($entiteId !== null && $entiteId > 0);

        try {
            $sqlTypes = 'SELECT i.type AS label, COUNT(*) AS total FROM incidents i';
            $paramsTypes = [];
            if ($hasEntite) {
                $sqlTypes .= ' INNER JOIN vehicules v ON v.id = i.vehicule_id WHERE v.entite_id = :entite';
                $paramsTypes['entite'] = $entiteId;
            }
            $sqlTypes .= ' GROUP BY i.type ORDER BY total DESC';

            $types = Database::all($sqlTypes, $paramsTypes);
            $parType = array_map(
                static fn (array $l): array => [
                    'label' => Incident::TYPES[(string) $l['label']] ?? (string) $l['label'],
                    'total' => (int) $l['total'],
                ],
                $types
            );

            $paramsTendance = [
                'du' => date('Y-m-01', strtotime("first day of -" . ($mois - 1) . " months")),
                'au' => date('Y-m-t'),
            ];
            if ($hasEntite) {
                $sqlTendance = 'SELECT DATE_FORMAT(i.date_incident, \'%Y-%m\') AS mois, COUNT(*) AS total
                                FROM incidents i
                                INNER JOIN vehicules v ON v.id = i.vehicule_id
                                WHERE i.date_incident BETWEEN :du AND :au AND v.entite_id = :entite
                                GROUP BY mois ORDER BY mois ASC';
                $paramsTendance['entite'] = $entiteId;
            } else {
                $sqlTendance = 'SELECT DATE_FORMAT(i.date_incident, \'%Y-%m\') AS mois, COUNT(*) AS total
                                FROM incidents i
                                WHERE i.date_incident BETWEEN :du AND :au
                                GROUP BY mois ORDER BY mois ASC';
            }

            $lignes = Database::all($sqlTendance, $paramsTendance);
        } catch (\PDOException $e) {
            Logger::error('Calcul de la sinistralité impossible', $e);
            return ['par_type' => [], 'tendance' => []];
        }

        $index = [];
        foreach ($lignes as $l) {
            $index[(string) $l['mois']] = (int) $l['total'];
        }
        $cursor = new \DateTimeImmutable('first day of -' . ($mois - 1) . ' months');
        for ($i = 0; $i < $mois; $i++) {
            $cle = $cursor->format('Y-m');
            $tendance[] = [
                'mois'  => self::libelleMois($cursor),
                'total' => $index[$cle] ?? 0,
            ];
            $cursor = $cursor->modify('+1 month');
        }

        return ['par_type' => $parType, 'tendance' => $tendance];
    }

    /** Séries de coût d'entretien pour les cartes KPI (mois courant / année). */
    public static function kpiCosts(?int $entiteId = null): array
    {
        $debutMois  = date('Y-m-01');
        $debutAnnee = date('Y-01-01');

        return [
            'mois'  => round(Maintenance::totalPeriode($debutMois, date('Y-m-t'), $entiteId), 2),
            'annee' => round(Maintenance::totalPeriode($debutAnnee, date('Y-m-t'), $entiteId), 2),
        ];
    }
}
