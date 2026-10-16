<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Core\Upload;
use Models\AuditLog;
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
        $this->view()->useScript('incidents.js');
        $filtres = Incident::filtersFromRequest();

        $this->render('incidents/index', [
            'base_url'          => $this->baseUrl(),
            'incidents'         => Incident::search($filtres),
            'filtres'           => $filtres,
            'vehicules'         => Vehicle::search([]),
            'entites'           => \Models\Dictionary::list('entites'),
            'types'             => Incident::TYPES,
            'statuts'           => Incident::STATUTS,
            'assurance_statuts' => Incident::ASSURANCE_STATUTS,
        ]);
    }

    /** Fiche d'un incident et gestion de ses pièces jointes. */
    public function show(): void
    {
        $this->guard();
        $this->view()->useScript('incidents.js');
        $id       = Request::int('id');
        $incident = $id > 0 ? Incident::find($id) : null;

        if ($incident === null) {
            Flash::add('danger', 'Incident introuvable.');
            $this->redirect('/incidents');
        }

        $this->render('incidents/show', [
            'base_url'          => $this->baseUrl(),
            'incident'          => $incident,
            'fichiers'          => Incident::files($id),
            'vehicules'         => Vehicle::search([]),
            'entites'           => \Models\Dictionary::list('entites'),
            'types'             => Incident::TYPES,
            'statuts'           => Incident::STATUTS,
            'assurance_statuts' => Incident::ASSURANCE_STATUTS,
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

        $id = Request::int('id');
        Csrf::verifyOrFail();
        Auth::requireIncidentPermission($id === 0);

        $payload = Incident::buildPayload();
        $erreurs = Incident::validate($payload);

        if ($erreurs !== []) {
            $this->repondreErreurs($erreurs, $id);
        }

        $creation = $id === 0;

        try {
            $vehicule = Vehicle::find((int) $payload['vehicule_id']);
            $immat    = $vehicule['immatriculation'] ?? null;

            if ($id > 0) {
                $existant = Incident::find($id);
                Incident::update($id, $payload);
                $diff = $existant !== null ? AuditLog::diff($existant, $payload) : ['apres' => $payload];
                AuditLog::modification(
                    'incident',
                    $id,
                    $immat,
                    "Modification de l'incident #$id" . ($immat ? " ($immat)" : ''),
                    $diff
                );
                Flash::add('success', 'L\'incident a été mis à jour.');
            } else {
                $id = Incident::create($payload);
                AuditLog::creation(
                    'incident',
                    $id,
                    $immat,
                    "Déclaration d'un incident pour le véhicule $immat",
                    $payload
                );
                Flash::add('success', 'L\'incident a été enregistré.');
            }
        } catch (\PDOException $e) {
            Logger::error('Enregistrement incident impossible', $e);
            $this->repondreErreurs(['Enregistrement impossible : vérifiez les données saisies.'], $id);
        }

        // Nouvel incident : ouverture de la fiche du véhicule concerné.
        if ($creation && $vehicule !== null) {
            $this->redirect('/vehicules/voir?id=' . (int) $payload['vehicule_id']);
        }

        $this->redirect('/incidents/voir?id=' . $id);
    }

    /** Suppression logique d'un incident (POST). */
    public function delete(): void
    {
        $this->guardPost();
        $id = Request::int('id');

        try {
            $incident = Incident::find($id);
            if ($incident === null) {
                Flash::add('danger', 'Incident introuvable.');
                $this->redirect('/incidents');
            }
            Incident::softDelete($id);
            $vehicule = Vehicle::find((int) $incident['vehicule_id']);
            $immat    = $vehicule['immatriculation'] ?? null;

            AuditLog::suppression(
                'incident',
                $id,
                $immat,
                "Archivage (suppression logique) de l'incident #$id" . ($immat ? " ($immat)" : ''),
                $incident
            );
            Flash::add('success', 'L\'incident a été archivé (masqué pour les non-administrateurs).');
        } catch (\PDOException $e) {
            Logger::error("Archivage incident #$id impossible", $e);
            Flash::add('danger', 'Archivage impossible.');
        }

        $this->redirect('/incidents');
    }

    /** Restauration d'un incident archivé (POST, admin). */
    public function restore(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);
        $id = Request::int('id');

        try {
            $incident = Incident::find($id);
            if ($incident === null) {
                Flash::add('danger', 'Incident introuvable.');
                $this->redirect('/incidents');
            }
            Incident::restore($id);
            $vehicule = Vehicle::find((int) $incident['vehicule_id']);
            $immat    = $vehicule['immatriculation'] ?? null;

            AuditLog::modification(
                'incident',
                $id,
                $immat,
                "Restauration de l'incident #$id" . ($immat ? " ($immat)" : '')
            );
            Flash::add('success', 'L\'incident a été restauré avec succès.');
            $this->redirect('/incidents/voir?id=' . $id);
        } catch (\Throwable $e) {
            Logger::error("Restauration incident #$id impossible", $e);
            Flash::add('danger', 'Restauration impossible.');
            $this->redirect('/incidents');
        }
    }

    /** Suppression définitive d'un incident et de ses pièces jointes (POST, admin). */
    public function forceDelete(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);
        $id = Request::int('id');

        try {
            $incident = Incident::find($id);
            if ($incident === null) {
                Flash::add('danger', 'Incident introuvable.');
                $this->redirect('/incidents');
            }
            Incident::forceDelete($id);
            $vehicule = Vehicle::find((int) $incident['vehicule_id']);
            $immat    = $vehicule['immatriculation'] ?? null;

            AuditLog::suppression(
                'incident',
                $id,
                $immat,
                "Suppression définitive de l'incident #$id" . ($immat ? " ($immat)" : ''),
                $incident
            );
            Flash::add('success', 'L\'incident et ses pièces jointes ont été définitivement supprimés.');
        } catch (\PDOException $e) {
            Logger::error("Suppression définitive incident #$id impossible", $e);
            Flash::add('danger', 'Suppression définitive impossible.');
        }

        $this->redirect('/incidents');
    }

    /** Dépôt d'une pièce jointe sur un incident (POST, multipart). */
    public function upload(): void
    {
        Csrf::verifyOrFail();
        Auth::requireUploadIncidentDocument();

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
            $fileId = Incident::addFile(
                $incidentId,
                (string) $resultat['chemin'],
                (string) ($fichier['name'] ?? 'fichier'),
                (string) $resultat['mime'],
                (int) $resultat['taille']
            );
            $immat = $incident['immatriculation'] ?? null;
            $nomFichier = (string) ($fichier['name'] ?? 'fichier');
            $ref = $incident['reference'] ?? "#$incidentId";
            AuditLog::creation(
                'document',
                $fileId > 0 ? $fileId : $incidentId,
                $immat,
                "Ajout de la pièce jointe '$nomFichier' au dossier de sinistre $ref" . ($immat ? " ($immat)" : ''),
                [
                    'incident_id'  => $incidentId,
                    'reference'    => $ref,
                    'nom_original' => $nomFichier,
                    'taille'       => (int) $resultat['taille'],
                ]
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

        $chemin = Upload::absolutePath(Incident::DOSSIER, (string) $fichier['nom_fichier']);
        if ($chemin === null) {
            $this->refuserApercu();
        }

        // Le type déclaré en base peut avoir été altéré : seul `finfo`, qui lit
        // les octets, fait autorité.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($chemin);
        if (!isset(Incident::MIMES[(string) $mime])) {
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

        $incidentId = (int) $fichier['incident_id'];
        $incident   = Incident::find($incidentId);
        $immat      = $incident['immatriculation'] ?? null;
        $nomFichier = (string) ($fichier['nom_original'] ?? 'fichier');
        $ref        = $incident['reference'] ?? "#$incidentId";

        try {
            Incident::deleteFile((int) $fichier['id']);
            AuditLog::suppression(
                'document',
                $id,
                $immat,
                "Suppression de la pièce jointe '$nomFichier' du dossier de sinistre $ref" . ($immat ? " ($immat)" : ''),
                $fichier
            );
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
