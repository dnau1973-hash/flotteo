<?php
declare(strict_types=1);

/**
 * Modale de saisie d'un incident.
 *
 * @var array $vehicules, $types, $statuts
 * @var array|null $entites, $assurance_statuts
 * @var int $vehicule_id
 * @var string $base_url
 */

use Core\Csrf;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$entites = $entites ?? \Models\Dictionary::list('entites');
$assurance_statuts = $assurance_statuts ?? \Models\Incident::ASSURANCE_STATUTS;
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
                        <select class="form-select" id="i_v_vehicule" name="vehicule_id" required
                                data-autocomplete data-autocomplete-placeholder="Rechercher : immatriculation, marque, modèle…">
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($vehicules as $v): ?>
                                <option value="<?= (int) $v['id'] ?>"
                                        data-entite-id="<?= (int) ($v['entite_id'] ?? 0) ?>"
                                        <?= (int) $vehicule_id === (int) $v['id'] ? 'selected' : '' ?>>
                                    <?= $e($v['immatriculation'] . ' — ' . $v['marque_nom'] . ' ' . $v['modele_nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="i_v_entite">Entité</label>
                        <select class="form-select" id="i_v_entite" name="entite_id">
                            <option value="">— Héritée du véhicule ou non définie —</option>
                            <?php foreach ($entites as $ent): ?>
                                <option value="<?= (int) $ent['id'] ?>"><?= $e($ent['nom'] . (!empty($ent['code']) ? ' (' . $ent['code'] . ')' : '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="i_v_type">Type d'incident</label>
                        <select class="form-select" id="i_v_type" name="type" required>
                            <?php foreach ($types as $cle => $libelle): ?>
                                <option value="<?= $e($cle) ?>"><?= $e($libelle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="i_v_date">Date de l'incident</label>
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon" aria-hidden="true">
                                    <path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" />
                                    <path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" />
                                </svg>
                            </span>
                            <input type="text" class="form-control" id="i_v_date" name="date_incident" required
                                   placeholder="DD-MM-YYYY" autocomplete="off" data-bs-toggle="datepicker">
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="i_v_statut">Statut du dossier</label>
                        <select class="form-select" id="i_v_statut" name="statut">
                            <?php foreach ($statuts as $cle => $libelle): ?>
                                <option value="<?= $e($cle) ?>"><?= $e($libelle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" for="i_v_lieu">Lieu précis</label>
                        <input type="text" class="form-control" id="i_v_lieu" name="lieu" maxlength="160"
                               placeholder="A7 — sortie 12, détails sur le sinistre">
                    </div>

                    <div class="col-12 col-md-6">
                        <span class="form-label d-block">Responsabilité</span>
                        <div class="d-flex gap-4 pt-1">
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="responsable" value="1" id="i_v_resp">
                                <span class="form-check-label">Véhicule responsable</span>
                            </label>
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="immatricule" value="1" id="i_v_immat">
                                <span class="form-check-label">Véhicule immatriculé</span>
                            </label>
                        </div>
                    </div>

                    <!-- Section Assurance & Prise en charge -->
                    <div class="col-12">
                        <div class="card bg-light-lt border">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="form-label fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                        <svg class="icon text-primary" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M12 3a12 12 0 0 0 8.5 3A12 12 0 0 1 12 21A12 12 0 0 1 3.5 6A12 12 0 0 0 12 3"/>
                                            <path d="M12 11v6"/><path d="M12 8h.01"/>
                                        </svg>
                                        Assurance &amp; Prise en charge
                                    </span>
                                </div>

                                <div class="row g-3">
                                    <!-- Ligne 1 : Statut et les deux cases à cocher avec libellés explicites -->
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="i_v_assurance_statut">Prise en charge assurance</label>
                                        <select class="form-select" id="i_v_assurance_statut" name="assurance_statut">
                                            <option value="">— Non renseigné —</option>
                                            <option value="prise_en_charge">Prise en charge</option>
                                            <option value="non_prise_en_charge">Non prise en charge</option>
                                        </select>
                                        <div class="text-secondary small mt-1">Statut de prise en charge.</div>
                                    </div>

                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="i_v_remb_attendu">Remboursement attendu</label>
                                        <div style="min-height: 36px;" class="d-flex align-items-center">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="remboursement_attendu" value="1" id="i_v_remb_attendu">
                                                <label class="form-check-label" for="i_v_remb_attendu">Remboursement attendu</label>
                                            </div>
                                        </div>
                                        <div class="text-secondary small mt-1">Dossier en attente d'indemnité.</div>
                                    </div>

                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="i_v_remb_percu">Remboursement perçu</label>
                                        <div style="min-height: 36px;" class="d-flex align-items-center">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="remboursement_percu" value="1" id="i_v_remb_percu">
                                                <label class="form-check-label fw-bold" for="i_v_remb_percu">Remboursement perçu</label>
                                            </div>
                                        </div>
                                        <div class="text-secondary small mt-1">Virement reçu et encaissé.</div>
                                    </div>

                                    <!-- Ligne 2 : Montants financiers parfaitement alignés en deux colonnes égales -->
                                    <div class="col-12" id="bloc-assurance-details" style="display: none;">
                                        <div class="row g-3 pt-3 border-top">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label d-flex align-items-center justify-content-between mb-1" for="i_v_montant_rembourse" id="label_montant_rembourse">
                                                    <span>Montant perçu de l'assurance</span>
                                                    <span class="text-danger fw-bold" id="etoile_montant_rembourse" style="display: none;" title="Obligatoire lorsque le remboursement est perçu">*</span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" step="0.01" min="0" class="form-control" id="i_v_montant_rembourse" name="montant_rembourse" placeholder="0.00">
                                                    <span class="input-group-text">€</span>
                                                </div>
                                                <div class="text-secondary small mt-1">Indemnité reçue de la compagnie.</div>
                                            </div>

                                            <div class="col-12 col-md-6">
                                                <label class="form-label d-flex align-items-center justify-content-between mb-1" for="i_v_montant_franchise">
                                                    <span>Montant de la franchise</span>
                                                </label>
                                                <div class="input-group">
                                                    <input type="number" step="0.01" min="0" class="form-control" id="i_v_montant_franchise" name="montant_franchise" placeholder="0.00">
                                                    <span class="input-group-text">€</span>
                                                </div>
                                                <div class="text-secondary small mt-1">Part restant à charge.</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Alerte montant perçu obligatoire -->
                                    <div class="col-12" id="alerte-montant-percu-requis" style="display: none;">
                                        <div class="alert alert-danger d-flex align-items-center mb-0" role="alert">
                                            <svg class="icon alert-icon me-2 flex-shrink-0" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" fill="none" stroke-width="2">
                                                <circle cx="12" cy="12" r="9"/>
                                                <line x1="12" y1="8" x2="12" y2="12"/>
                                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                                            </svg>
                                            <div>
                                                <strong>Montant obligatoire :</strong> Lorsque la case <em>« Remboursement perçu »</em> est cochée, vous devez obligatoirement saisir le <strong>Montant perçu de l'assurance</strong> (montant supérieur à 0 €).
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Alerte de blocage de clôture -->
                                    <div class="col-12" id="alerte-cloture-remboursement" style="display: none;">
                                        <div class="alert alert-danger d-flex align-items-center mb-0" role="alert">
                                            <svg class="icon alert-icon me-2 flex-shrink-0" viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" fill="none" stroke-width="2">
                                                <circle cx="12" cy="12" r="9"/>
                                                <line x1="12" y1="8" x2="12" y2="12"/>
                                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                                            </svg>
                                            <div>
                                                <strong>Clôture impossible :</strong> Ce dossier fait l'objet d'un remboursement d'assurance attendu mais non encore perçu. Veuillez cocher <em>« Remboursement perçu »</em> pour pouvoir clôturer l'incident, ou modifier le statut.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="i_v_description">Descriptif de l'événement</label>
                        <textarea class="form-control" id="i_v_description" name="description" rows="4" maxlength="4000"
                                  placeholder="Circonstances, dommages constatés, tiers impliqués…"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" id="btn-enregistrer-incident">Enregistrer l'incident</button>
            </div>
        </form>
    </div>
</div>
