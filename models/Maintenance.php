<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;
use Core\Request;

/**
 * Historique des révisions et prestations d'entretien.
 */
final class Maintenance
{
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

    /** Coût cumulé d'entretien sur une période. */
    public static function totalPeriode(string $du, string $au): float
    {
        return (float) Database::scalar(
            'SELECT COALESCE(SUM(cout_ht), 0) FROM maintenances WHERE date_operation BETWEEN :du AND :au',
            ['du' => $du, 'au' => $au],
            0.0
        );
    }
}
