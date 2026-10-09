<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Core\Response;
use Core\Upload;
use Models\Dictionary;
use Models\Maintenance;
use Models\Vehicle;
use Models\VehicleDocument;


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
            'documents'    => VehicleDocument::forVehicle($id),
            'docTypes'     => VehicleDocument::TYPES,
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
     * Dépôt d'un document sur un véhicule (POST, multipart).
     */
    public function uploadDocument(): void
    {
        $this->guardPost();

        $vehiculeId = Request::int('vehicule_id');
        $vehicule   = $vehiculeId > 0 ? Vehicle::find($vehiculeId) : null;
        if ($vehicule === null) {
            Flash::add('danger', 'Véhicule introuvable.');
            $this->redirect('/vehicules');
        }

        $type           = (string) Request::input('type', VehicleDocument::TYPE_AUTRE);
        $titre          = (string) Request::input('titre', '');
        $dateExpiration = Request::date('date_expiration') ?: null;

        $fichier = $_FILES['fichier'] ?? null;
        if (!is_array($fichier)) {
            Flash::add('danger', 'Aucun fichier reçu.');
            $this->redirect('/vehicules/voir?id=' . $vehiculeId);
        }

        $depot = Upload::store($fichier, VehicleDocument::DOSSIER, VehicleDocument::MIMES);
        if ($depot['erreur'] !== null) {
            Flash::add('danger', (string) $depot['erreur']);
            $this->redirect('/vehicules/voir?id=' . $vehiculeId);
        }

        try {
            VehicleDocument::add(
                $vehiculeId,
                $type,
                $titre,
                $dateExpiration,
                (string) $depot['chemin'],
                (string) ($fichier['name'] ?? 'document'),
                (string) $depot['mime'],
                (int) $depot['taille']
            );
            Flash::add('success', 'Document ajouté avec succès au véhicule.');
        } catch (\PDOException $e) {
            Logger::error("Enregistrement document véhicule impossible pour #$vehiculeId", $e);
            Upload::remove(VehicleDocument::DOSSIER, (string) $depot['chemin']);
            Flash::add('danger', 'Le document n\'a pas pu être enregistré.');
        }

        $this->redirect('/vehicules/voir?id=' . $vehiculeId);
    }

    /**
     * Téléchargement d'un document d'un véhicule.
     */
    public function downloadDocument(): void
    {
        $this->guard();
        $doc = VehicleDocument::find(Request::int('id'));
        if ($doc === null) {
            Flash::add('danger', 'Document introuvable.');
            $this->redirect('/vehicules');
        }

        $chemin = Upload::absolutePath(VehicleDocument::DOSSIER, (string) $doc['nom_fichier']);
        if ($chemin === null) {
            Flash::add('danger', 'Fichier absent du stockage.');
            $this->redirect('/vehicules/voir?id=' . (int) $doc['vehicule_id']);
        }

        $nom = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $doc['nom_original']) ?: 'document';
        header('Content-Type: ' . (string) $doc['mime']);
        header('Content-Length: ' . (string) filesize($chemin));
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($chemin);
        exit;
    }

    /**
     * Aperçu en ligne d'un document (pour visionneuse modale).
     */
    public function previewDocument(): void
    {
        $this->guard();
        $doc = VehicleDocument::find(Request::int('id'));
        if ($doc === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Document introuvable.';
            exit;
        }

        $chemin = Upload::absolutePath(VehicleDocument::DOSSIER, (string) $doc['nom_fichier']);
        if ($chemin === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Fichier absent du stockage.';
            exit;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($chemin);
        if (!isset(VehicleDocument::MIMES[(string) $mime])) {
            Logger::error("Aperçu document véhicule refusé: type réel $mime pour le doc #" . (int) $doc['id']);
            http_response_code(415);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Aperçu indisponible pour ce type de fichier.';
            exit;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($chemin));
        header('Content-Disposition: inline');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cache-Control: private, no-store');
        readfile($chemin);
        exit;
    }

    /**
     * Suppression d'un document (POST).
     */
    public function deleteDocument(): void
    {
        $this->guardPost();
        $id  = Request::int('id');
        $doc = VehicleDocument::find($id);
        if ($doc === null) {
            Flash::add('danger', 'Document introuvable.');
            $this->redirect('/vehicules');
        }

        $vehiculeId = (int) $doc['vehicule_id'];
        if (VehicleDocument::delete($id)) {
            Flash::add('success', 'Le document a été supprimé.');
        } else {
            Flash::add('danger', 'Suppression impossible.');
        }

        $this->redirect('/vehicules/voir?id=' . $vehiculeId);
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
        Response::redirect(Url::to($id > 0 ? '/vehicules/voir?id=' . $id : '/vehicules'));
    }
}
