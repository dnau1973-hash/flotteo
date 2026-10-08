<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Core\Upload;
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
        $filtres    = Maintenance::filtersFromRequest();
        $entretiens = Maintenance::search($filtres);

        $this->render('maintenance/index', [
            'base_url'    => $this->baseUrl(),
            'entretiens'  => $entretiens,
            'nb_fichiers' => Maintenance::compterFichiers(
                array_column($entretiens, 'id')
            ),
            'filtres'     => $filtres,
            'vehicules'   => Vehicle::search([]),
            'types'       => Dictionary::list('types_intervention'),
            'categories'  => Dictionary::TYPES['types_intervention']['categories'] ?? [],
            'total_ht'    => array_sum(array_map(
                static fn (array $m): float => (float) $m['cout_ht'],
                $entretiens
            )),
        ]);
    }

    /** Fiche d'une prestation : détail, véhicule et pièces jointes. */
    public function show(): void
    {
        $this->guard();
        $id         = Request::int('id');
        $entretien  = $id > 0 ? Maintenance::find($id) : null;

        if ($entretien === null) {
            Flash::add('warning', 'Prestation introuvable.');
            $this->redirect('/entretien');
        }

        $this->render('maintenance/show', [
            'base_url'  => $this->baseUrl(),
            'entretien' => $entretien,
            'fichiers'  => Maintenance::files($id),
            'vehicule'  => Vehicle::find((int) $entretien['vehicule_id']),
            'types'     => Dictionary::list('types_intervention'),
            'vehicules' => Vehicle::search([]),
            'vehicule_id' => (int) $entretien['vehicule_id'],
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
            Logger::error('Enregistrement entretien impossible', $e);
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
            Logger::error("Suppression entretien #$id impossible", $e);
            Flash::add('danger', 'Suppression impossible.');
        }

        $this->redirect('/entretien');
    }

    /**
     * Dépôt d'une pièce jointe sur une prestation (POST, multipart).
     *
     * Si l'enregistrement en base échoue, le fichier déjà déposé est retiré :
     * une ligne absente laisserait un octet orphelin sur le disque, invisible
     * pour l'administrateur et jamais nettoyé.
     */
    public function upload(): void
    {
        $this->guardPost();

        $id         = Request::int('maintenance_id');
        $entretien  = $id > 0 ? Maintenance::find($id) : null;
        if ($entretien === null) {
            Flash::add('danger', 'Prestation introuvable.');
            $this->redirect('/entretien');
        }

        $fichier = $_FILES['fichier'] ?? null;
        if (!is_array($fichier)) {
            Flash::add('danger', 'Aucun fichier reçu.');
            $this->redirect('/entretien/voir?id=' . $id);
        }

        $depot = Upload::store($fichier, Maintenance::DOSSIER, Maintenance::MIMES);
        if ($depot['erreur'] !== null) {
            Flash::add('danger', (string) $depot['erreur']);
            $this->redirect('/entretien/voir?id=' . $id);
        }

        try {
            Maintenance::addFile(
                $id,
                (string) $depot['chemin'],
                (string) ($fichier['name'] ?? 'fichier'),
                (string) $depot['mime'],
                (int) $depot['taille']
            );
            Flash::add('success', 'Pièce jointe ajoutée à la prestation.');
        } catch (\PDOException $e) {
            Logger::error('Enregistrement pièce jointe entretien impossible', $e);
            Upload::remove(Maintenance::DOSSIER, (string) $depot['chemin']);
            Flash::add('danger', 'La pièce jointe n\'a pas pu être enregistrée.');
        }

        $this->redirect('/entretien/voir?id=' . $id);
    }

    /** Téléchargement d'une pièce jointe. */
    public function download(): void
    {
        $this->guard();
        $fichier = Maintenance::findFile(Request::int('id'));
        if ($fichier === null) {
            Flash::add('danger', 'Fichier introuvable.');
            $this->redirect('/entretien');
        }

        $chemin = Upload::absolutePath(Maintenance::DOSSIER, (string) $fichier['nom_fichier']);
        if ($chemin === null) {
            Flash::add('danger', 'Fichier absent du stockage.');
            $this->redirect('/entretien/voir?id=' . (int) $fichier['maintenance_id']);
        }

        $nom = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $fichier['nom_original']) ?: 'piece_jointe';
        header('Content-Type: ' . (string) $fichier['mime']);
        header('Content-Length: ' . (string) filesize($chemin));
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($chemin);
        exit;
    }

    /**
     * Aperçu d'une pièce jointe, servi en ligne pour la visionneuse.
     *
     * Séparé de `download()`, qui force `Content-Disposition: attachment` : le
     * navigateur y enregistrerait le fichier au lieu de l'afficher.
     *
     * Images et PDF sont tous deux servis ici. L'aperçu d'incident refuse le PDF
     * parce qu'il ne vise que la vignette ; ici le PDF est précisément ce que la
     * visionneuse doit afficher, et un `<iframe>` ne rend rien d'une réponse
     * d'attachement.
     *
     * Verrous :
     * - le type est contrôlé sur le fichier réel et non sur la ligne en base,
     *   qui n'est qu'une déclaration ;
     * - `nosniff` empêche le navigateur de deviner un contenu qu'il ne reconnaît
     *   pas, et qui deviendrait du HTML exécutable ;
     * - `sandbox` neutralise toute capacité d'accès au document appelant : ni
     *   script, ni feuille de style, ni lecture du stockage du navigateur ;
     * - `private, no-store` interdit la conservation de la pièce au-delà de la
     *   session, le fichier n'étant pas public.
     */
    public function preview(): void
    {
        $this->guard();
        $fichier = Maintenance::findFile(Request::int('id'));
        if ($fichier === null) {
            $this->refuserApercu();
        }

        $chemin = Upload::absolutePath(Maintenance::DOSSIER, (string) $fichier['nom_fichier']);
        if ($chemin === null) {
            $this->refuserApercu();
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($chemin);
        if (!isset(Maintenance::MIMES[(string) $mime])) {
            Logger::error("Apercu entretien refuse: type reel $mime pour la piece #" . (int) $fichier['id']);
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

    /** Suppression d'une pièce jointe (POST). */
    public function deleteFile(): void
    {
        $this->guardPost();
        $id      = Request::int('id');
        $fichier = Maintenance::findFile($id);
        if ($fichier === null) {
            Flash::add('danger', 'Fichier introuvable.');
            $this->redirect('/entretien');
        }

        $prestation = (int) $fichier['maintenance_id'];
        if (Maintenance::deleteFile($id)) {
            Flash::add('success', 'La pièce jointe a été supprimée.');
        } else {
            Flash::add('danger', 'Suppression impossible.');
        }

        $this->redirect('/entretien/voir?id=' . $prestation);
    }

    /** Réponse neutre pour une pièce jointe introuvable. */
    private function refuserApercu(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Fichier introuvable.';
        exit;
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
