<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;

/**
 * Modèle Mileage : Gestion rapide des relevés mensuels d'odomètre par véhicule.
 */
final class Mileage
{
    /**
     * Retourne la matrice des véhicules avec leur relevé pour une période donnée (format 'YYYY-MM').
     * Calcule automatiquement le km précédent (M-1 ou km_initial) et le delta parcouru.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function matriceMensuelle(string $periode): array
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m', $periode) ?: new \DateTimeImmutable('first day of this month');
        $periodePrec = $dt->modify('-1 month')->format('Y-m');

        try {
            $vehicules = Database::all(
                "SELECT v.id, v.immatriculation,
                        COALESCE(v.kilometrage, 0) AS kilometrage,
                        mo.nom AS modele_nom, ma.nom AS marque_nom,
                        e.nom AS entite_nom
                 FROM vehicules v
                 JOIN modeles mo ON mo.id = v.modele_id
                 JOIN marques ma ON ma.id = mo.marque_id
                 JOIN entites e  ON e.id  = v.entite_id
                 WHERE v.statut = 'actif'
                 ORDER BY v.immatriculation ASC"
            );
        } catch (\PDOException $e) {
            $vehicules = Database::all(
                "SELECT v.id, v.immatriculation,
                        0 AS kilometrage,
                        mo.nom AS modele_nom, ma.nom AS marque_nom,
                        e.nom AS entite_nom
                 FROM vehicules v
                 JOIN modeles mo ON mo.id = v.modele_id
                 JOIN marques ma ON ma.id = mo.marque_id
                 JOIN entites e  ON e.id  = v.entite_id
                 WHERE v.statut = 'actif'
                 ORDER BY v.immatriculation ASC"
            );
        }

        $matrice = [];
        foreach ($vehicules as $v) {
            $vid = (int) $v['id'];

            // 1. Relevé de la période courante
            $releveActuel = null;
            try {
                $releveActuel = Database::one(
                    'SELECT id, index_km, distance_mois, statut, updated_at 
                     FROM releves_odometre 
                     WHERE vehicule_id = :vid AND periode = :p',
                    ['vid' => $vid, 'p' => $periode]
                );
            } catch (\PDOException $e) { \Core\Logger::error($e->getMessage()); }

            // 2. Relevé de la période précédente (M-1) ou plus récent antérieur
            $kmPrecedent = null;
            try {
                $kmPrecedent = Database::scalar(
                    'SELECT index_km FROM releves_odometre 
                     WHERE vehicule_id = :vid AND periode < :p 
                     ORDER BY periode DESC LIMIT 1',
                    ['vid' => $vid, 'p' => $periode]
                );
            } catch (\PDOException $e) { \Core\Logger::error($e->getMessage()); }

            if ($kmPrecedent === null) {
                // Pas de relevé antérieur dans releves_odometre :
                // Utiliser le champ `kilometrage` de la table vehicules comme référence de départ
                $kmVehicule = (int) ($v['kilometrage'] ?? 0);
                if ($kmVehicule > 0) {
                    $kmPrecedent = $kmVehicule;
                } else {
                    // Repli sur le dernier entretien enregistré si disponible
                    try {
                        $kmEntretien = Database::scalar(
                            'SELECT kilometrage FROM maintenances 
                             WHERE vehicule_id = :vid ORDER BY date_operation DESC LIMIT 1',
                            ['vid' => $vid]
                        );
                        $kmPrecedent = $kmEntretien !== null ? (int) $kmEntretien : 0;
                    } catch (\PDOException $e) { \Core\Logger::error($e->getMessage()); 
                        $kmPrecedent = 0;
                    }
                }
            } else {
                $kmPrecedent = (int) $kmPrecedent;
            }

            $indexActuel = $releveActuel !== null ? (int) $releveActuel['index_km'] : null;
            $distance = $indexActuel !== null ? max(0, $indexActuel - $kmPrecedent) : 0;
            $estSaisi = $indexActuel !== null;
            $verrouille = ($releveActuel['statut'] ?? '') === 'verrouille';

            $matrice[] = [
                'vehicule_id'     => $vid,
                'immatriculation' => (string) $v['immatriculation'],
                'marque_modele'   => (string) $v['marque_nom'] . ' ' . (string) $v['modele_nom'],
                'entite'          => (string) $v['entite_nom'],
                'km_precedent'    => $kmPrecedent,
                'index_actuel'    => $indexActuel,
                'distance'        => $distance,
                'est_saisi'       => $estSaisi,
                'verrouille'      => $verrouille,
                'releve_id'       => $releveActuel['id'] ?? null,
            ];
        }

        return $matrice;
    }

    /**
     * Enregistre ou met à jour l'index d'odomètre d'un véhicule pour un mois.
     * Met à jour le kilométrage global dans la table 'vehicules' qui servira de référence suivante.
     * Recalcule la distance du mois et propage le recalcul au mois suivant si existant.
     */
    public static function sauvegarderIndex(int $vehiculeId, string $periode, int $indexKm): array
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m', $periode) ?: new \DateTimeImmutable('first day of this month');
        $periodePrec = $dt->modify('-1 month')->format('Y-m');
        $periodeSuiv = $dt->modify('+1 month')->format('Y-m');

        // Déterminer le km précédent
        $kmPrecedent = null;
        try {
            $kmPrecedent = Database::scalar(
                'SELECT index_km FROM releves_odometre 
                 WHERE vehicule_id = :vid AND periode < :p 
                 ORDER BY periode DESC LIMIT 1',
                ['vid' => $vehiculeId, 'p' => $periode]
            );
        } catch (\PDOException $e) { \Core\Logger::error($e->getMessage()); }

        if ($kmPrecedent === null) {
            // Priorité au champ `kilometrage` de la table 'vehicules'
            try {
                $kmVehicule = Database::scalar(
                    'SELECT kilometrage FROM vehicules WHERE id = :id',
                    ['id' => $vehiculeId]
                );
                $kmPrecedent = $kmVehicule !== null ? (int) $kmVehicule : 0;
            } catch (\PDOException $e) { \Core\Logger::error($e->getMessage()); 
                $kmPrecedent = 0;
            }
        } else {
            $kmPrecedent = (int) $kmPrecedent;
        }

        $distance = max(0, $indexKm - $kmPrecedent);

        // Insertion ou mise à jour du relevé mensuel
        Database::run(
            'INSERT INTO releves_odometre (vehicule_id, periode, index_km, distance_mois, statut)
             VALUES (:vid, :p, :idx, :dist, :st)
             ON DUPLICATE KEY UPDATE 
                index_km = :idx2, 
                distance_mois = :dist2',
            [
                'vid'   => $vehiculeId,
                'p'     => $periode,
                'idx'   => $indexKm,
                'dist'  => $distance,
                'st'    => 'saisi',
                'idx2'  => $indexKm,
                'dist2' => $distance,
            ]
        );

        // Mise à jour du champ kilometrage dans la table 'vehicules'
        // Il est mis à jour à chaque saisie et servira de kilométrage de début de référence pour la saisie suivante
        try {
            $dernierKmMax = (int) Database::scalar(
                'SELECT MAX(index_km) FROM releves_odometre WHERE vehicule_id = :vid',
                ['vid' => $vehiculeId],
                $indexKm
            );
            Database::run(
                'UPDATE vehicules SET kilometrage = :km WHERE id = :id',
                ['km' => max($dernierKmMax, $indexKm), 'id' => $vehiculeId]
            );
        } catch (\PDOException $e) {
            Logger::error('Mise à jour du champ kilometrage sur vehicules impossible', $e);
        }

        // Propagation au mois suivant (si déjà saisi)
        $releveSuiv = Database::one(
            'SELECT id, index_km FROM releves_odometre WHERE vehicule_id = :vid AND periode = :p',
            ['vid' => $vehiculeId, 'p' => $periodeSuiv]
        );
        if ($releveSuiv !== null) {
            $distSuiv = max(0, (int) $releveSuiv['index_km'] - $indexKm);
            Database::run(
                'UPDATE releves_odometre SET distance_mois = :dist WHERE id = :id',
                ['dist' => $distSuiv, 'id' => $releveSuiv['id']]
            );
        }

        return [
            'success'      => true,
            'index_km'     => $indexKm,
            'km_precedent' => $kmPrecedent,
            'distance'     => $distance,
        ];
    }

    /**
     * Bascule l'état de verrouillage d'un relevé.
     */
    public static function toggleVerrou(int $vehiculeId, string $periode): bool
    {
        $releve = Database::one(
            'SELECT id, statut FROM releves_odometre WHERE vehicule_id = :vid AND periode = :p',
            ['vid' => $vehiculeId, 'p' => $periode]
        );
        if ($releve === null) {
            return false;
        }

        $nouveauStatut = $releve['statut'] === 'verrouille' ? 'saisi' : 'verrouille';
        Database::run(
            'UPDATE releves_odometre SET statut = :st WHERE id = :id',
            ['st' => $nouveauStatut, 'id' => $releve['id']]
        );

        return $nouveauStatut === 'verrouille';
    }

    /**
     * Retourne l'historique sur 12 mois glissants pour le graphique d'un véhicule.
     *
     * @return array{labels: string[], distances: int[]}
     */
    public static function historique12Mois(int $vehiculeId): array
    {
        $labels = [];
        $periodes = [];
        $curseur = (new \DateTimeImmutable('first day of this month'))->modify('-11 months');

        for ($i = 0; $i < 12; $i++) {
            $p = $curseur->format('Y-m');
            $periodes[] = $p;
            $labels[] = self::labelMoisCourt($curseur->format('m')) . ' ' . $curseur->format('y');
            $curseur = $curseur->modify('+1 month');
        }

        try {
            $releves = Database::all(
                'SELECT periode, distance_mois FROM releves_odometre 
                 WHERE vehicule_id = :vid AND periode IN (' . implode(',', array_map(fn($p) => "'$p'", $periodes)) . ')',
                ['vid' => $vehiculeId]
            );
        } catch (\PDOException $e) {
            $releves = [];
        }

        $map = [];
        foreach ($releves as $r) {
            $map[(string) $r['periode']] = (int) $r['distance_mois'];
        }

        $distances = [];
        foreach ($periodes as $p) {
            $distances[] = $map[$p] ?? 0;
        }

        return [
            'labels'    => $labels,
            'distances' => $distances,
        ];
    }

    private static function labelMoisCourt(string $m): string
    {
        $mois = [
            '01' => 'Janv', '02' => 'Févr', '03' => 'Mars', '04' => 'Avr',
            '05' => 'Mai',  '06' => 'Juin', '07' => 'Juil', '08' => 'Août',
            '09' => 'Sept', '10' => 'Oct',  '11' => 'Nov',  '12' => 'Déc',
        ];
        return $mois[$m] ?? $m;
    }
}
