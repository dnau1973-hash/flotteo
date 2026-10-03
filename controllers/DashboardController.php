<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
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

        $stats = Vehicle::stats();
        $couts = Stat::kpiCosts();

        $this->render('dashboard/index', [
            'base_url'   => $this->baseUrl(),
            'stats'      => $stats,
            'couts'      => $couts,
            'echeances'  => Vehicle::search(['echeance' => '90']),
            'immobilises' => Vehicle::search(['statut' => 'immobilise']),
            'entites'    => Dictionary::list('entites'),
            'loueurs'    => Dictionary::list('loueurs'),
        ]);
    }
}
