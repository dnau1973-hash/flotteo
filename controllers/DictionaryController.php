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

        try {
            if ($id > 0) {
                Dictionary::update($type, $id, $payload);
                Flash::add('success', 'Entrée mise à jour.');
            } else {
                Dictionary::create($type, $payload);
                Flash::add('success', 'Entrée ajoutée à la table de paramétrage.');
            }
        } catch (\PDOException $e) {
            Core\Logger::error("Enregistrement dictionnaire $type impossible", $e);
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
