<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Request;
use Models\Stat;
use Models\Vehicle;

/**
 * Endpoints JSON légers consommés par ApexCharts.
 */
final class ApiController extends Controller
{
    private function entiteFilter(): ?int
    {
        $id = Request::int('entite');
        return ($id !== null && $id > 0) ? $id : null;
    }

    public function kpis(): void
    {
        $this->guard();
        $entiteId = $this->entiteFilter();
        $this->json([
            'success' => true,
            'data'    => [
                'flotte'    => Vehicle::stats($entiteId),
                'couts'     => Stat::kpiCosts($entiteId),
                'incidents' => Stat::incidents(12, $entiteId)['par_type'],
            ],
        ]);
    }

    /** Échéancier des sorties de flotte sur 12 mois. */
    public function exitSchedule(): void
    {
        $this->guard();
        $series = Stat::exitSchedule(12, $this->entiteFilter());
        $this->json([
            'success' => true,
            'data'    => [
                'etiquettes' => array_column($series, 'mois'),
                'series'     => [['name' => 'Restitutions prévues', 'data' => array_column($series, 'total')]],
            ],
        ]);
    }

    /** Répartition de la flotte : entite | loueur | lieu | marque | statut. */
    public function fleetSplit(): void
    {
        $this->guard();
        $dimension = (string) Request::input('dimension', 'entite');
        if (!in_array($dimension, ['entite', 'loueur', 'lieu', 'marque', 'statut'], true)) {
            $dimension = 'entite';
        }
        $filtre = (string) Request::input('filtre', '');
        $data   = Stat::fleetSplit($dimension, $filtre, $this->entiteFilter());

        $this->json([
            'success' => true,
            'data'    => [
                'etiquettes' => array_column($data, 'label'),
                'series'     => [['name' => 'Véhicules', 'data' => array_column($data, 'total')]],
                'total'      => array_sum(array_column($data, 'total')),
            ],
        ]);
    }

    /** Évolution des dépenses d'entretien empilées par typologie. */
    public function maintenanceCosts(): void
    {
        $this->guard();
        $data = Stat::maintenanceCosts(12, $this->entiteFilter());
        $series = array_map(
            static fn (array $s): array => ['name' => ucfirst($s['categorie']), 'data' => $s['data']],
            $data['series']
        );
        $this->json(['success' => true, 'data' => ['etiquettes' => $data['etiquettes'], 'series' => $series]]);
    }

    /** TCO moyen d'entretien par modèle de véhicule. */
    public function tcoByModel(): void
    {
        $this->guard();
        $data = Stat::tcoByModel(12, $this->entiteFilter());
        $this->json([
            'success' => true,
            'data'    => [
                'etiquettes' => array_column($data, 'label'),
                'series'     => [['name' => 'Coût moyen / véhicule', 'data' => array_column($data, 'moyenne')]],
                'detail'     => $data,
            ],
        ]);
    }

    /** Matrice des incidents : répartition par type et tendance annuelle. */
    public function incidents(): void
    {
        $this->guard();
        $data = Stat::incidents(12, $this->entiteFilter());
        $this->json([
            'success' => true,
            'data'    => [
                'par_type'  => array_column($data['par_type'], 'total'),
                'type_labels' => array_column($data['par_type'], 'label'),
                'mois'      => array_column($data['tendance'], 'mois'),
                'tendance'  => array_column($data['tendance'], 'total'),
            ],
        ]);
    }

    /** Liste compacte des véhicules (alimentation des sélecteurs en cascade). */
    public function vehicleList(): void
    {
        $this->guard();
        $vehicules = Vehicle::search(['statut' => (string) Request::input('statut', '')]);
        $this->json([
            'success' => true,
            'data'    => array_map(
                static fn (array $v): array => [
                    'id'            => (int) $v['id'],
                    'immatriculation' => $v['immatriculation'],
                    'libelle'       => $v['marque_nom'] . ' ' . $v['modele_nom'] . ' — ' . $v['immatriculation'],
                ],
                $vehicules
            ),
        ]);
    }
}
