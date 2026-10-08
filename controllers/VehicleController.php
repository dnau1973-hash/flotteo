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
    /** Liste filtrable du parc avec pagination. */
    public function index(): void
    {
        $this->guard();
        $filtres = Vehicle::filtersFromRequest();

        $page = max(1, Request::int('page', 1));
        $parPage = Request::int('par_page', 25);
        if (!in_array($parPage, [15, 25, 50, 100], true)) {
            $parPage = 25;
        }

        $total = Vehicle::count($filtres);
        $nbPages = max(1, (int) ceil($total / $parPage));
        if ($page > $nbPages && $total > 0) {
            $page = $nbPages;
        }
        $offset = ($page - 1) * $parPage;

        $entites = Dictionary::list('entites');
        usort($entites, static fn(array $a, array $b): int => strnatcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? '')));

        $loueurs = Dictionary::list('loueurs');
        usort($loueurs, static fn(array $a, array $b): int => strnatcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? '')));

        $lieux = Dictionary::list('lieux');
        usort($lieux, static fn(array $a, array $b): int => strnatcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? '')));

        $this->render('vehicles/index', [
            'base_url'        => $this->baseUrl(),
            'vehicules'       => Vehicle::search($filtres, $parPage, $offset),
            'filtres'         => $filtres,
            'statuts'         => Vehicle::STATUTS,
            'entites'         => $entites,
            'loueurs'         => $loueurs,
            'lieux'           => $lieux,
            'total'           => $total,
            'page'            => $page,
            'nbPages'         => $nbPages,
            'parPage'         => $parPage,
            'offset'          => $offset,
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
            'historiqueKm' => \Models\Mileage::historique12Mois($id),
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
            Logger::error('Enregistrement véhicule impossible', $e);
            $msg = 'Enregistrement impossible : ' . $e->getMessage();
            $this->repondreErreurs([$msg], $id);
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
            Logger::error("Suppression véhicule #$id impossible", $e);
            Flash::add('danger', 'Suppression impossible : ce véhicule est référencé.');
        }

        $this->redirect('/vehicules');
    }

    /**
     * Importation en masse de véhicules depuis un fichier CSV (POST).
     */
    public function importerCsv(): void
    {
        $this->guardPost();

        $fichier = $_FILES['fichier_csv'] ?? null;
        if (!is_array($fichier) || empty($fichier['tmp_name']) || (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Flash::add('danger', 'Veuillez sélectionner un fichier CSV valide.');
            $this->redirect('/vehicules');
        }

        $mode = (string) Request::input('mode_import', 'upsert');
        $defaults = [
            'modele_id' => Request::int('modele_id'),
            'entite_id' => Request::int('entite_id'),
            'loueur_id' => Request::int('loueur_id'),
            'lieu_id'   => Request::int('lieu_id'),
        ];

        try {
            $resultat = Vehicle::importCsv($fichier['tmp_name'], $defaults, $mode);

            $msg = sprintf(
                'Import terminé : %d véhicule(s) créé(s), %d mis à jour sur %d ligne(s) traitée(s).',
                $resultat['crees'],
                $resultat['modifies'],
                $resultat['total']
            );
            if ($resultat['ignores'] > 0) {
                $msg .= sprintf(' (%d ignoré(s))', $resultat['ignores']);
            }
            Flash::add('success', $msg);

            if ($resultat['erreurs'] !== []) {
                $maxAffichage = 5;
                $details = array_slice($resultat['erreurs'], 0, $maxAffichage);
                $reste = count($resultat['erreurs']) - $maxAffichage;
                $msgErreur = count($resultat['erreurs']) . " ligne(s) avec avertissement : " . implode(' | ', $details);
                if ($reste > 0) {
                    $msgErreur .= " (+ $reste autre(s))";
                }
                Flash::add('warning', $msgErreur);
            }
        } catch (\Throwable $e) {
            Logger::error('Échec import CSV véhicules', $e);
            Flash::add('danger', 'Une erreur est survenue lors de la lecture du fichier CSV : ' . $e->getMessage());
        }

        $this->redirect('/vehicules');
    }

    /**
     * Téléchargement d'un fichier CSV exemple avec les 4 colonnes attendues.
     */
    public function modeleCsv(): never
    {
        $this->guard();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="modele_import_vehicules.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out !== false) {
            // Émission du BOM UTF-8 pour Excel
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['immatriculation', 'duree_contrat', 'km_maxi', 'date_entree'], ';');
            fputcsv($out, ['AA-123-AA', '36', '120000', date('Y-m-d', strtotime('-1 year'))], ';');
            fputcsv($out, ['BB-456-BB', '48', '150000', date('Y-m-d', strtotime('-6 months'))], ';');
            fputcsv($out, ['CC-789-CC', '24', '90000', date('Y-m-d')], ';');
            fclose($out);
        }
        exit;
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
