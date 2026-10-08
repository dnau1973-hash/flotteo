<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Request;
use Services\AgendaService;

/**
 * Agenda : calendrier des échéances, révisions et immobilisations.
 *
 * Deux actions, comme le tableau de bord :
 *
 *  - `index()` rend la page et la charge initiale, sans appel réseau depuis la
 *    vue ;
 *  - `evenements()` sert la plage affichée au calendrier, en JSON, au fil de la
 *    navigation.
 *
 * L'endpoint est en `GET` et protégé par `guard()` seul : aucune donnée n'est
 * écrite, donc aucun jeton CSRF n'est exigé. Le rôle de lecture suffit, comme
 * pour le graphique des restitutions prévues.
 */
final class AgendaController extends Controller
{
    /**
     * Borne la plage demandée.
     *
     * FullCalendar demande la vue élargie des jours voisins, ce qui est
     * normal. On refuse néanmoins les plages démesurées — un `?du=1900-01-01`
     * transformerait l'agenda en requête de plusieurs années sur l'historique
     * d'entretien — et l'on ramène un intervalle aberrant à deux ans, de quoi
     * couvrir largement n'importe quelle navigation au bouton précédent/suivant.
     */
    private const PLAGE_MAX_JOURS = 730;
    private const PLAGE_DEFAUT_JOURS = 45;

    public function index(): void
    {
        $this->guard();

        // La page s'ouvre sur le mois en cours : la plage doit donc couvrir ce
        // mois et les semaines qui débordent de part et d'autre.
        [$du, $au] = self::plageMoisCourant();
        $evenements = AgendaService::evenements($du, $au);

        $this->render('agenda/index', [
            'base_url'  => $this->baseUrl(),
            'types'     => AgendaService::TYPES,
            'compte'    => AgendaService::compter($evenements),
            'total'     => count($evenements),
            'du'        => $du,
            'au'        => $au,
            // Repris par le script pour dater le premier rendu sans aller
            // chercher l'information ailleurs.
            'jour'      => date('Y-m-d'),
        ]);
    }

    /** Événements d'une plage, au format attendu par FullCalendar. */
    public function evenements(): void
    {
        $this->guard();

        [$du, $au] = self::plageDepuisRequete();
        $types     = self::typesDepuisRequete();

        $evenements = AgendaService::evenements($du, $au, $types);

        $this->json([
            'success' => true,
            'data'    => $evenements,
            'meta'    => [
                'du'      => $du,
                'au'      => $au,
                'compte'  => AgendaService::compter($evenements),
                'types'   => $types,
            ],
        ]);
    }

    /**
     * Plage issue de la requête, bornée et ordonnée.
     *
     * @return array{0: string, 1: string}
     */
    private static function plageDepuisRequete(): array
    {
        $du = Request::date('du');
        $au = Request::date('au');

        if ($du === '' || $au === '') {
            return self::plageMoisCourant();
        }

        if ($au < $du) {
            [$du, $au] = [$au, $du];
        }

        $debut = strtotime($du);
        $fin   = strtotime($au);

        if ($debut === false || $fin === false) {
            return self::plageMoisCourant();
        }

        if (($fin - $debut) / 86400 > self::PLAGE_MAX_JOURS) {
            $du = date('Y-m-d', $fin - self::PLAGE_MAX_JOURS * 86400);
        }

        return [$du, $au];
    }

    /** @return array{0: string, 1: string} */
    private static function plageMoisCourant(): array
    {
        return [
            date('Y-m-01', strtotime('first day of previous month')),
            date('Y-m-t', strtotime('last day of next month')),
        ];
    }

    /**
     * Familles demandées, par le paramètre `types` (liste séparée par des virgules).
     *
     * @return list<string>
     */
    private static function typesDepuisRequete(): array
    {
        $brut = (string) Request::input('types', '');
        if ($brut === '') {
            return [];
        }

        $demandes = array_values(array_filter(array_map(
            static fn (string $t): string => trim($t),
            explode(',', $brut)
        )));

        // Une famille inconnue est ignorée plutôt que renvoyée telle quelle : la
        // liste blanche est ici la même que celle de la légende.
        $retenues = array_values(array_intersect($demandes, array_keys(AgendaService::TYPES)));

        return $retenues;
    }
}