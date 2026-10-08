<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Models\Mileage;
use Models\Vehicle;

/**
 * Gestion de la saisie rapide de l'odomètre mensuel.
 */
final class MileageController extends Controller
{
    /** Matrice mensuelle de saisie rapide. */
    public function index(): void
    {
        $this->guard();

        $periode = trim((string) Request::input('periode', ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
            $periode = date('Y-m');
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m', $periode) ?: new \DateTimeImmutable('first day of this month');
        $periodePrecedente = $dt->modify('-1 month')->format('Y-m');
        $periodeSuivante   = $dt->modify('+1 month')->format('Y-m');

        $moisNoms = [
            '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
            '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août',
            '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre',
        ];
        $labelPeriode = ($moisNoms[$dt->format('m')] ?? '') . ' ' . $dt->format('Y');

        try {
            $lignes = Mileage::matriceMensuelle($periode);
        } catch (\PDOException $e) {
            Logger::error('Lecture de la matrice kilométrique impossible', $e);
            $lignes = [];
        }

        $nbManquants = 0;
        foreach ($lignes as $l) {
            if (!$l['est_saisi']) {
                $nbManquants++;
            }
        }

        $this->render('mileage/index', [
            'base_url'          => $this->baseUrl(),
            'periode'           => $periode,
            'periodePrecedente' => $periodePrecedente,
            'periodeSuivante'   => $periodeSuivante,
            'labelPeriode'      => $labelPeriode,
            'lignes'            => $lignes,
            'nbManquants'       => $nbManquants,
        ]);
    }

    /** Enregistrement AJAX rapide en ligne (inline editing). */
    public function sauvegarderRapide(): void
    {
        $this->guardPost();

        $raw = Request::rawBody();
        $data = json_decode($raw, true) ?? [];

        $vehiculeId = (int) ($data['vehicule_id'] ?? Request::int('vehicule_id'));
        $periode    = trim((string) ($data['periode'] ?? Request::input('periode', '')));
        $indexKm    = (int) ($data['index_km'] ?? Request::int('index_km'));

        if ($vehiculeId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $periode) || $indexKm < 0) {
            $this->ko('Paramètres de saisie invalides.');
        }

        try {
            $res = Mileage::sauvegarderIndex($vehiculeId, $periode, $indexKm);
            $this->ok('Relevé enregistré.', $res);
        } catch (\PDOException $e) {
            Logger::error('Enregistrement rapide odomètre impossible', $e);
            $this->ko('Erreur base de données lors de l\'enregistrement.');
        }
    }

    /** Verrouille ou déverrouille un relevé en AJAX. */
    public function toggleVerrou(): void
    {
        $this->guardPost();

        $raw = Request::rawBody();
        $data = json_decode($raw, true) ?? [];

        $vehiculeId = (int) ($data['vehicule_id'] ?? Request::int('vehicule_id'));
        $periode    = trim((string) ($data['periode'] ?? Request::input('periode', '')));

        if ($vehiculeId <= 0 || $periode === '') {
            $this->ko('Requête invalide.');
        }

        try {
            $estVerrouille = Mileage::toggleVerrou($vehiculeId, $periode);
            $this->ok('État du verrouillage mis à jour.', ['verrouille' => $estVerrouille]);
        } catch (\PDOException $e) {
            Logger::error('Bascule du verrou odomètre impossible', $e);
            $this->ko('Erreur lors de la modification du verrou.');
        }
    }

    /** Données JSON pour le graphique 12 mois de la fiche véhicule. */
    public function apiGraphique(): void
    {
        $this->guard();
        $vehiculeId = Request::int('id');

        if ($vehiculeId <= 0) {
            $this->ko('Véhicule non spécifié.');
        }

        try {
            $donnees = Mileage::historique12Mois($vehiculeId);
            $this->json(['success' => true] + $donnees);
        } catch (\PDOException $e) {
            Logger::error('Lecture historique graphique odomètre impossible', $e);
            $this->ko('Erreur lors du chargement des données graphiques.');
        }
    }
}
