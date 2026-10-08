<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Request;
use Models\Dictionary;
use Models\Stat;
use Models\Vehicle;

/**
 * Tableau de bord : synthèse opérationnelle et financière.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->guard();

        $entiteId = Request::int('entite');
        if ($entiteId !== null && $entiteId <= 0) {
            $entiteId = null;
        }

        $stats = Vehicle::stats($entiteId);
        $couts = Stat::kpiCosts($entiteId);

        $filtreEcheances = ['echeance' => '90'];
        $filtreImmob = ['statut' => 'immobilise'];
        if ($entiteId !== null) {
            $filtreEcheances['entite'] = $entiteId;
            $filtreImmob['entite'] = $entiteId;
        }

        $this->render('dashboard/index', [
            'base_url'    => $this->baseUrl(),
            'stats'       => $stats,
            'couts'       => $couts,
            'echeances'   => Vehicle::search($filtreEcheances),
            'immobilises' => Vehicle::search($filtreImmob),
            'entites'     => Dictionary::list('entites'),
            'loueurs'     => Dictionary::list('loueurs'),
            'entite_id'   => $entiteId,
        ]);
    }
}
