<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Core\Response;
use Models\Dictionary;
use Models\Maintenance;
use Models\Vehicle;


/**
 * Consultation et cycle de vie du parc de véhicules.
 */
final class VehicleController extends Controller
{
    /** Liste filtrable du parc. */
    public function index(): void
    {
        $this->guard();
        $filtres = Vehicle::filtersFromRequest();

        $this->render('vehicles/index', [
            'base_url' => $this->baseUrl(),
            'vehicules' => Vehicle::search($filtres),
            'filtres'   => $filtres,
            'statuts'   => Vehicle::STATUTS,
            'entites'   => Dictionary::list('entites'),
            'loueurs'   => Dictionary::list('loueurs'),
            'lieux'     => Dictionary::list('lieux'),
        ]);
    }

    /** Fiche détaillée d'un véhicule : identité, entretien, incidents. */
    public function show(): void
    {
        $this->guard();
        $id       = Request::int('id');
        $vehicule = $id > 0 ? Vehicle::find($id) : null;

        if ($vehicule === null) {
            Flash::add('danger', 'Véhicule introuvable.');
            $this->redirect('/vehicules');
        }

        $this->render('vehicles/show', [
            'base_url'    => $this->baseUrl(),
            'vehicule'    => $vehicule,
            'entretiens'  => Maintenance::search(['vehicule_id' => $id]),
            'nomenclature' => [
                'modeles' => Dictionary::list('modeles'),
                'entites' => Dictionary::list('entites'),
                'loueurs' => Dictionary::list('loueurs'),
                'lieux'   => Dictionary::list('lieux'),
            ],
            'statuts' => Vehicle::STATUTS,
        ]);
    }

    /** Création ou mise à jour d'un véhicule (POST). */
    public function save(): void
    {
        $this->guardPost();
        $id      = Request::int('id');
        $payload = Vehicle::buildPayload();
        $erreurs = Vehicle::validate($payload);

        if (Vehicle::immatriculationExists($payload['immatriculation'], $id > 0 ? $id : null)) {
            $erreurs[] = 'Cette immatriculation est déjà enregistrée dans le parc.';
        }

        if ($erreurs !== []) {
            $this->repondreErreurs($erreurs, $id);
        }

        try {
            if ($id > 0) {
                Vehicle::update($id, $payload);
                Flash::add('success', 'La fiche véhicule a été mise à jour.');
            } else {
                $id = Vehicle::create($payload);
                Flash::add('success', 'Le véhicule a été ajouté au parc.');
            }
        } catch (\PDOException $e) {
            Core\Logger::error('Enregistrement véhicule impossible', $e);
            $this->repondreErreurs(['Enregistrement impossible : vérifiez les données saisies.'], $id);
        }

        $this->redirect($id > 0 ? '/vehicules/voir?id=' . $id : '/vehicules');
    }

    /** Suppression d'un véhicule (POST). */
    public function delete(): void
    {
        $this->guardPost();
        $id = Request::int('id');

        try {
            $vehicule = Vehicle::find($id);
            if ($vehicule === null) {
                Flash::add('danger', 'Véhicule introuvable.');
                $this->redirect('/vehicules');
            }
            Vehicle::delete($id);
            Flash::add('success', 'Le véhicule ' . $vehicule['immatriculation'] . ' a été supprimé.');
        } catch (\PDOException $e) {
            Core\Logger::error("Suppression véhicule #$id impossible", $e);
            Flash::add('danger', 'Suppression impossible : ce véhicule est référencé.');
        }

        $this->redirect('/vehicules');
    }

    /**
     * Répond en JSON pour une requête AJAX, sinon en redirection avec flash.
     *
     * @param string[] $erreurs
     */
    private function repondreErreurs(array $erreurs, int $id): never
    {
        $message = implode(' ', $erreurs);
        if (Request::isJson()) {
            $this->ko($message);
        }
        Flash::add('danger', $message);
        Response::redirect($this->baseUrl() . ($id > 0 ? '/vehicules/voir?id=' . $id : '/vehicules'));
    }
}
