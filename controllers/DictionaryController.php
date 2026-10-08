<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Flash;
use Core\Logger;
use Core\Request;
use Models\Dictionary;


/**
 * Administration des tables de paramétrage (dictionnaires).
 */
final class DictionaryController extends Controller
{
    /** Formulaire générique de gestion d'un dictionnaire. */
    public function index(): void
    {
        $this->guard(Auth::ROLE_ADMIN);
        $type = Request::param('type') !== '' ? Request::param('type') : (string) Request::input('type', 'marques');

        $def = Dictionary::definition($type);
        if ($def === null) {
            Flash::add('danger', 'Table de paramétrage inconnue.');
            $this->redirect('/admin/dictionnaires');
        }

        $this->render('admin/dictionnaire', [
            'base_url' => $this->baseUrl(),
            'type'     => $type,
            'def'      => $def,
            'types'    => Dictionary::TYPES,
            'entrees'  => Dictionary::list($type),
            'sources'  => $this->sources(),
            'incidents_ids' => $type === 'types_intervention',
        ]);
    }

    /** Création ou mise à jour d'une entrée (POST). */
    public function save(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);
        $type = (string) $this->paramType();
        $def  = Dictionary::definition($type);

        if ($def === null) {
            $this->repondreErreurs(['Table de paramétrage inconnue.'], $type);
        }

        $id      = Request::int('id');
        $payload = Dictionary::buildPayload($type);
        $erreurs = Dictionary::validate($type, $payload, $id > 0 ? $id : null);

        if ($erreurs !== []) {
            $this->repondreErreurs($erreurs, $type);
        }

        $nouveauFichier = null;
        $ancienFichier = '';
        $champFichier = null;
        $dossierUpload = null;

        if ($type === 'marques') {
            $champFichier = 'logo';
            $dossierUpload = Dictionary::DOSSIER_LOGO;
        } elseif ($type === 'modeles') {
            $champFichier = 'photo';
            $dossierUpload = Dictionary::DOSSIER_PHOTO;
        }

        if ($champFichier !== null && $dossierUpload !== null) {
            $supprimerFichier = Request::int($champFichier . '_supprimer') === 1;
            if ($id > 0) {
                try {
                    $table = $def['table'];
                    $ancienFichier = (string) \Core\Database::scalar("SELECT $champFichier FROM $table WHERE id = :id", ['id' => $id]);
                } catch (\PDOException) {}
            }

            $fichierUpload = $_FILES[$champFichier] ?? null;
            if (is_array($fichierUpload) && isset($fichierUpload['error']) && (int) $fichierUpload['error'] !== UPLOAD_ERR_NO_FILE) {
                $depot = \Core\Upload::store($fichierUpload, $dossierUpload, Dictionary::MIMES_LOGO);
                if ($depot['erreur'] !== null) {
                    $labelFichier = $def['etiquette'][$champFichier] ?? ucfirst($champFichier);
                    $this->repondreErreurs([$labelFichier . ' : ' . $depot['erreur']], $type);
                }
                $nouveauFichier = (string) $depot['chemin'];
            }

            if ($nouveauFichier !== null) {
                $payload[$champFichier] = $nouveauFichier;
            } elseif ($supprimerFichier && $id > 0) {
                $payload[$champFichier] = null;
            }
        }

        try {
            if ($id > 0) {
                Dictionary::update($type, $id, $payload);
                if ($champFichier !== null && $dossierUpload !== null) {
                    if ($nouveauFichier !== null && $ancienFichier !== '' && $ancienFichier !== $nouveauFichier) {
                        \Core\Upload::remove($dossierUpload, $ancienFichier);
                    } elseif (array_key_exists($champFichier, $payload) && $payload[$champFichier] === null && $ancienFichier !== '') {
                        \Core\Upload::remove($dossierUpload, $ancienFichier);
                    }
                }
                Flash::add('success', 'Entrée mise à jour.');
            } else {
                Dictionary::create($type, $payload);
                Flash::add('success', 'Entrée ajoutée à la table de paramétrage.');
            }
        } catch (\PDOException $e) {
            if ($nouveauFichier !== null && $dossierUpload !== null) {
                \Core\Upload::remove($dossierUpload, $nouveauFichier);
            }

            Logger::error("Enregistrement dictionnaire $type impossible", $e);
            $this->repondreErreurs(['Enregistrement impossible : valeur déjà utilisée ou contrainte violée.'], $type);
        }

        $this->redirect('/admin/dictionnaires/' . $type);
    }

    /** Suppression d'une entrée (POST). */
    public function delete(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);
        $type = (string) $this->paramType();
        $id   = Request::int('id');

        if (Dictionary::definition($type) === null) {
            $this->repondreErreurs(['Table de paramétrage inconnue.'], $type);
        }

        if (Dictionary::delete($type, $id)) {
            Flash::add('success', 'Entrée supprimée.');
        } else {
            Flash::add('danger', 'Suppression impossible : cette entrée est encore utilisée par un véhicule ou un entretien.');
        }

        $this->redirect('/admin/dictionnaires/' . $type);
    }

    /**
     * Le type de dictionnaire est porté par le segment de route /admin/dictionnaires/{type}.
     */
    private function paramType(): string
    {
        $type = Request::param('type');
        return Dictionary::definition($type) !== null ? $type : '';
    }

    /** Listes de référence affichées dans les formulaires. */
    private function sources(): array
    {
        return ['marques' => Dictionary::list('marques')];
    }

    /** @param string[] $erreurs */
    private function repondreErreurs(array $erreurs, string $type): never
    {
        $message = implode(' ', $erreurs);
        if (Request::isJson()) {
            $this->ko($message);
        }
        Flash::add('danger', $message);
        $this->redirect('/admin/dictionnaires/' . $type);
    }
}
