<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Models\Dictionary;
use Models\Maintenance;
use Models\Vehicle;


/**
 * Historique des révisions et prestations d'entretien.
 */
final class MaintenanceController extends Controller
{
    /** Liste des entretiens, filtrable par véhicule, catégorie et période. */
    public function index(): void
    {
        $this->guard();
        $filtres = Maintenance::filtersFromRequest();

        $this->render('maintenance/index', [
            'base_url'  => $this->baseUrl(),
            'entretiens' => Maintenance::search($filtres),
            'filtres'   => $filtres,
            'vehicules' => Vehicle::search([]),
            'types'     => Dictionary::list('types_intervention'),
            'categories' => Dictionary::TYPES['types_intervention']['categories'] ?? [],
            'total_ht'  => array_sum(array_map(
                static fn (array $m): float => (float) $m['cout_ht'],
                Maintenance::search($filtres)
            )),
        ]);
    }

    /** Création ou mise à jour d'un entretien (POST). */
    public function save(): void
    {
        $this->guardPost();
        $id      = Request::int('id');
        $payload = Maintenance::buildPayload();
        $erreurs = Maintenance::validate($payload);

        if ($erreurs !== []) {
            $this->repondreErreurs($erreurs);
        }

        try {
            if ($id > 0) {
                Maintenance::update($id, $payload);
                Flash::add('success', 'La prestation a été mise à jour.');
            } else {
                Maintenance::create($payload);
                Flash::add('success', 'La prestation a été enregistrée.');
            }
        } catch (\PDOException $e) {
            Core\Logger::error('Enregistrement entretien impossible', $e);
            $this->repondreErreurs(['Enregistrement impossible : vérifiez les données saisies.']);
        }

        $this->redirect('/entretien');
    }

    /** Suppression d'un entretien (POST). */
    public function delete(): void
    {
        $this->guardPost();
        $id = Request::int('id');

        try {
            $entretien = Maintenance::find($id);
            if ($entretien === null) {
                Flash::add('danger', 'Prestation introuvable.');
                $this->redirect('/entretien');
            }
            Maintenance::delete($id);
            Flash::add('success', 'La prestation a été supprimée.');
        } catch (\PDOException $e) {
            Core\Logger::error("Suppression entretien #$id impossible", $e);
            Flash::add('danger', 'Suppression impossible.');
        }

        $this->redirect('/entretien');
    }

    /** @param string[] $erreurs */
    private function repondreErreurs(array $erreurs): never
    {
        $message = implode(' ', $erreurs);
        if (Request::isJson()) {
            $this->ko($message);
        }
        Flash::add('danger', $message);
        $this->redirect('/entretien');
    }
}
