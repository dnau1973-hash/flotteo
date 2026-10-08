<?php
declare(strict_types=1);

/**
 * Modale de saisie d'un incident.
 *
 * @var array $vehicules, $types, $statuts
 * @var int $vehicule_id
 * @var string $base_url
 */

use Core\Csrf;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="modal modal-blur fade" id="modal-incident" tabindex="-1" role="dialog" aria-hidden="true"
     aria-labelledby="titre-incident">
    <div class="modal-dialog modal-lg" role="document">
        <form class="modal-content" method="post" action="<?= $e($base_url . '/incidents/enregistrer') ?>" id="formulaire-incident">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="0" id="incident-id">

            <div class="modal-header">
                <h5 class="modal-title" id="titre-incident">Signaler un incident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="i_v_vehicule">Véhicule</label>
                        <select class="form-select" id="i_v_vehicule" name="vehicule_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($vehicules as $v): ?>
                                <option value="<?= (int) $v['id'] ?>" <?= (int) $vehicule_id === (int) $v['id'] ? 'selected' : '' ?>>
                                    <?= $e($v['immatriculation'] . ' — ' . $v['marque_nom'] . ' ' . $v['modele_nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label required" for="i_v_type">Type d'incident</label>
                        <select class="form-select" id="i_v_type" name="type" required>
                            <?php foreach ($types as $cle => $libelle): ?>
                                <option value="<?= $e($cle) ?>"><?= $e($libelle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label required" for="i_v_date">Date de l'incident</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true">
                                    <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" />
                                    <path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" />
                                </svg>
                            </span>
                            <input type="text" class="form-control" id="i_v_date" name="date_incident" required
                                   placeholder="YYYY-MM-DD" autocomplete="off" data-bs-toggle="datepicker">
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="i_v_lieu">Lieu précis</label>
                        <input type="text" class="form-control" id="i_v_lieu" name="lieu" maxlength="160"
                               placeholder="A7 — sortie 12, précise sur le sinistre">
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label" for="i_v_statut">Statut du dossier</label>
                        <select class="form-select" id="i_v_statut" name="statut">
                            <?php foreach ($statuts as $cle => $libelle): ?>
                                <option value="<?= $e($cle) ?>"><?= $e($libelle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <span class="form-label d-block">Responsabilité</span>
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="responsable" value="1" id="i_v_resp">
                            <span class="form-check-label">Véhicule responsable</span>
                        </label>
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="immatricule" value="1" id="i_v_immat">
                            <span class="form-check-label">Véhicule immatriculé</span>
                        </label>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="i_v_description">Descriptif de l'événement</label>
                        <textarea class="form-control" id="i_v_description" name="description" rows="4" maxlength="4000"
                                  placeholder="Circonstances, dommages constatés, tiers involved…"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer l'incident</button>
            </div>
        </form>
    </div>
</div>
