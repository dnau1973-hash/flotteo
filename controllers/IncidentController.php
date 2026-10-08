<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Core\Upload;
use Models\Incident;
use Models\Vehicle;


/**
 * Incidents, sinistres et pièces jointes (constats, factures, photographies).
 */
final class IncidentController extends Controller
{
    /** Liste filtrable des incidents. */
    public function index(): void
    {
        $this->guard();
        $filtres = Incident::filtersFromRequest();

        $this->render('incidents/index', [
            'base_url'   => $this->baseUrl(),
            'incidents'  => Incident::search($filtres),
            'filtres'    => $filtres,
            'vehicules'  => Vehicle::search([]),
            'types'      => Incident::TYPES,
            'statuts'    => Incident::STATUTS,
        ]);
    }

    /** Fiche d'un incident et gestion de ses pièces jointes. */
    public function show(): void
    {
        $this->guard();
        $id       = Request::int('id');
        $incident = $id > 0 ? Incident::find($id) : null;

        if ($incident === null) {
            Flash::add('danger', 'Incident introuvable.');
            $this->redirect('/incidents');
        }

        $this->render('incidents/show', [
            'base_url' => $this->baseUrl(),
            'incident' => $incident,
            'fichiers' => Incident::files($id),
            'vehicules' => Vehicle::search([]),
            'types'    => Incident::TYPES,
            'statuts'  => Incident::STATUTS,
        ]);
    }

    /** Création ou mise à jour d'un incident (POST). */
    public function save(): void
    {
        // Avant `guardPost()` : au-delà de `post_max_size`, PHP vide `$_POST` et
        // `$_FILES` sans erreur, et le contrôle CSRF échoue en premier sur un
        // jeton pourtant envoyé.
        if (Request::corpsTronque()) {
            $limite = (string) Request::postMaxSize();
            Logger::error("Formulaire incident tronque par post_max_size ($limite)");
            $this->repondreErreurs([
                sprintf("Fichier refusé : la taille de l'envoi dépasse la limite du serveur (%s).", $limite),
            ], Request::int('id'));
        }

        $this->guardPost();
        $id      = Request::int('id');
        $payload = Incident::buildPayload();
        $erreurs = Incident::validate($payload);

        if ($erreurs !== []) {
            $this->repondreErreurs($erreurs, $id);
        }

        try {
            if ($id > 0) {
                Incident::update($id, $payload);
                Flash::add('success', 'L\'incident a été mis à jour.');
            } else {
                $id = Incident::create($payload);
                Flash::add('success', 'L\'incident a été enregistré.');
            }
        } catch (\PDOException $e) {
            Logger::error('Enregistrement incident impossible', $e);
            $this->repondreErreurs(['Enregistrement impossible : vérifiez les données saisies.'], $id);
        }

        $this->redirect('/incidents/voir?id=' . $id);
    }

    /** Suppression d'un incident et de ses pièces jointes (POST). */
    public function delete(): void
    {
        $this->guardPost();
        $id = Request::int('id');

        try {
            if (Incident::find($id) === null) {
                Flash::add('danger', 'Incident introuvable.');
                $this->redirect('/incidents');
            }
            Incident::delete($id);
            Flash::add('success', 'L\'incident et ses pièces jointes ont été supprimés.');
        } catch (\PDOException $e) {
            Logger::error("Suppression incident #$id impossible", $e);
            Flash::add('danger', 'Suppression impossible.');
        }

        $this->redirect('/incidents');
    }

    /** Dépôt d'une pièce jointe sur un incident (POST, multipart). */
    public function upload(): void
    {
        $this->guardPost();

        $incidentId = Request::int('incident_id');
        $incident   = $incidentId > 0 ? Incident::find($incidentId) : null;
        if ($incident === null) {
            $this->finir(false, 'Incident introuvable.', $incidentId);
        }

        $fichier = $_FILES['fichier'] ?? null;
        if (!is_array($fichier)) {
            $this->finir(false, 'Aucun fichier reçu.', $incidentId);
        }

        $resultat = Upload::store($fichier, Incident::DOSSIER);
        if ($resultat['erreur'] !== null) {
            Flash::add('danger', $resultat['erreur']);
            $this->redirect('/incidents/voir?id=' . $incidentId);
        }

        try {
            Incident::addFile(
                $incidentId,
                (string) $resultat['chemin'],
                (string) ($fichier['name'] ?? 'fichier'),
                (string) $resultat['mime'],
                (int) $resultat['taille']
            );
            Flash::add('success', 'Pièce jointe ajoutée au dossier.');
        } catch (\PDOException $e) {
            Logger::error('Enregistrement pièce jointe impossible', $e);
            Upload::remove(Incident::DOSSIER, (string) $resultat['chemin']);
            Flash::add('danger', 'La pièce jointe n\'a pas pu être enregistrée.');
        }

        $this->redirect('/incidents/voir?id=' . $incidentId);
    }

    /** Téléchargement sécurisé d'une pièce jointe. */
    public function download(): void
    {
        $this->guard();
        $fichier = Incident::findFile(Request::int('id'));
        if ($fichier === null) {
            Flash::add('danger', 'Fichier introuvable.');
            $this->redirect('/incidents');
        }

        $chemin = Upload::absolutePath(Incident::DOSSIER, (string) $fichier['nom_fichier']);
        if ($chemin === null) {
            Flash::add('danger', 'Fichier absent du stockage.');
            $this->redirect('/incidents/voir?id=' . (int) $fichier['incident_id']);
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
     * Aperçu d'une pièce jointe image, servi dans la page.
     *
     * Séparé de `download()`, qui force `Content-Disposition: attachment` : le
     * navigateur y enregistrerait le fichier au lieu de l'afficher, et une
     * vignette ne s'affiche jamais depuis une réponse d'attachement.
     *
     * Trois verrous, tous nécessaires :
     * - `Incident::MIMES_VIGNETTE` écarte les PDF, seul type non-image autorisé au
     *   téléversement ; le contrôle est refait sur le fichier réel, la ligne en
     *   base n'étant qu'une déclaration ;
     * - `nosniff` empêche le navigateur de sniffer un contenu qu'il ne
     *   reconnaîtrait pas ;
     * - `Content-Security-Policy: default-src 'none'` neutralise toute ressource
     *   externe : une image ne charge ni script ni feuille de style, et la page
     *   qui l'affiche n'est pas exposée si un octet du fichier était hostile.
     */
    public function preview(): void
    {
        $this->guard();
        $fichier = Incident::findFile(Request::int('id'));
        if ($fichier === null) {
            $this->refuserApercu();
        }

        if (!Incident::estImage($fichier)) {
            Flash::add('warning', 'Aperçu disponible uniquement pour les images.');
            $this->redirect('/incidents/voir?id=' . (int) $fichier['incident_id']);
        }

        $chemin = Upload::absolutePath(Incident::DOSSIER, (string) $fichier['nom_fichier']);
        if ($chemin === null) {
            $this->refuserApercu();
        }

        // Le type déclaré en base peut avoir été altéré : seul `finfo`, qui lit
        // les octets, fait autorité. Un fichier dont le contenu ne correspond
        // plus à une image raster est refusé sans message — une vignette
        // cassée nuirait moins que l'affichage d'un contenu arbitraire.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($chemin);
        if (!isset(Incident::MIMES_VIGNETTE[(string) $mime])) {
            Logger::error("Apercu refuse: type reel $mime pour la piece #" . (int) $fichier['id']);
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
        // Pièce jointe privée : ni le navigateur ni un mandataire ne doit la
        // conserver au-delà de la session.
        header('Cache-Control: private, no-store');
        readfile($chemin);
        exit;
    }

    /** Réponse neutre pour une pièce jointe introuvable. */
    private function refuserApercu(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Fichier introuvable.';
        exit;
    }

    /** Suppression d'une pièce jointe (POST). */
    public function deleteFile(): void
    {
        $this->guardPost();
        $fichier = Incident::findFile(Request::int('id'));
        if ($fichier === null) {
            Flash::add('danger', 'Fichier introuvable.');
            $this->redirect('/incidents');
        }

        try {
            Incident::deleteFile((int) $fichier['id']);
            Flash::add('success', 'Pièce jointe supprimée.');
        } catch (\PDOException $e) {
            Logger::error('Suppression pièce jointe impossible', $e);
            Flash::add('danger', 'Suppression impossible.');
        }

        $this->redirect('/incidents/voir?id=' . (int) $fichier['incident_id']);
    }

    /** @param string[] $erreurs */
    private function repondreErreurs(array $erreurs, int $id): never
    {
        $message = implode(' ', $erreurs);
        if (Request::isJson()) {
            $this->ko($message);
        }
        Flash::add('danger', $message);
        $this->redirect($id > 0 ? '/incidents/voir?id=' . $id : '/incidents');
    }

    /** Fin d'action d'upload : JSON en AJAX, redirection sinon. */
    private function finir(bool $succes, string $message, int $incidentId): never
    {
        if (Request::isJson()) {
            $succes ? $this->ok($message) : $this->ko($message);
        }
        Flash::add($succes ? 'success' : 'danger', $message);
        $this->redirect('/incidents' . ($incidentId > 0 ? '/voir?id=' . $incidentId : ''));
    }
}
