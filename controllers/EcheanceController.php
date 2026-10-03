<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Services\AlertService;

/**
 * Échéancier des fins de contrat.
 *
 * Page opérationnelle : elle restitue le même contenu que l'aperçu qui figurait
 * auparavant dans les paramètres, mais outreach de l'écran d'administration.
 * Elle est donc accessible au rôle de lecture, comme le graphique des
 * restitutions prévues du tableau de bord.
 *
 * L'ordre de préparation des données incombe au contrôleur : la vue se limite
 * à l'échappement et à la mise en forme.
 */
final class EcheanceController extends Controller
{
    /** Échéancier complet, du plus urgent au plus lointain. */
    public function index(): void
    {
        $this->guard();

        $echeances = self::aplatir(AlertService::echeancier());

        $this->render('echeances/index', [
            'base_url'  => $this->baseUrl(),
            'paliers'   => AlertService::paliers(),
            'echeances' => $echeances,
            'parPalier' => self::compterParPalier($echeances),
            'sous30'    => self::compterSous($echeances, 30),
            'sous90'    => self::compterSous($echeances, 90),
            'prochain'  => $echeances[0] ?? null,
        ]);
    }

    /**
     * Fusionne les groupes par palier en une liste unique, ordonnée par urgence
     * croissante : le véhicule le plus proche d'échéance remonte en tête, quel
     * que soit son palier.
     *
     * @param  list<array{palier:int,label:string,vehicules:list<array<string,mixed>>}> $groupes
     * @return list<array<string,mixed>>
     */
    private static function aplatir(array $groupes): array
    {
        $echeances = [];
        foreach ($groupes as $groupe) {
            foreach ($groupe['vehicules'] as $vehicule) {
                $echeances[] = $vehicule + [
                    'palier'      => $groupe['palier'],
                    'palier_label' => $groupe['label'],
                ];
            }
        }

        usort(
            $echeances,
            static fn (array $a, array $b): int => (int) $a['jours_restants'] <=> (int) $b['jours_restants']
        );

        return $echeances;
    }

    /**
     * Effectif par palier, pour les boutons de filtre.
     *
     * @param  list<array<string,mixed>> $echeances
     * @return array<int, int>
     */
    private static function compterParPalier(array $echeances): array
    {
        $compte = [];
        foreach ($echeances as $echeance) {
            $palier = (int) $echeance['palier'];
            $compte[$palier] = ($compte[$palier] ?? 0) + 1;
        }

        return $compte;
    }

    /**
     * @param list<array<string,mixed>> $echeances
     */
    private static function compterSous(array $echeances, int $jours): int
    {
        return count(array_filter(
            $echeances,
            static fn (array $e): bool => (int) $e['jours_restants'] <= $jours
        ));
    }
}