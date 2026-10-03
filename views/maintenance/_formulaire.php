<?php
declare(strict_types=1);

/**
 * Modale de saisie d'une prestation d'entretien.
 *
 * @var array $vehicules, $types
 * @var int $vehicule_id  véhicule pré-sélectionné
 * @var string $base_url
 */

use Core\Csrf;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="modal modal-blur fade" id="modal-entretien" tabindex="-1" role="dialog" aria-hidden="true"
     aria-labelledby="titre-entretien">
    <div class="modal-dialog modal-lg" role="document">
        <form class="modal-content" method="post" action="<?= $e($base_url . '/entretien/enregistrer') ?>" id="formulaire-entretien">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="0" id="entretien-id">

            <div class="modal-header">
                <h5 class="modal-title" id="titre-entretien">Nouvelle prestation d'entretien</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="e_vehicule">Véhicule</label>
                        <select class="form-select" id="e_vehicule" name="vehicule_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($vehicules as $v): ?>
                                <option value="<?= (int) $v['id'] ?>" <?= (int) $vehicule_id === (int) $v['id'] ? 'selected' : '' ?>>
                                    <?= $e($v['immatriculation'] . ' — ' . $v['marque_nom'] . ' ' . $v['modele_nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="e_type">Type de prestation</label>
                        <select class="form-select" id="e_type" name="type_intervention_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"><?= $e($t['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label required" for="e_date">Date d'intervention</label>
                        <input type="date" class="form-control" id="e_date" name="date_operation" required>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label" for="e_km">Kilométrage</label>
                        <input type="number" class="form-control" id="e_km" name="kilometrage" min="0" step="1" value="0">
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label required" for="e_ht">Coût HT (€)</label>
                        <input type="number" class="form-control" id="e_ht" name="cout_ht" min="0" step="0.01" required value="0">
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label" for="e_ttc">Coût TTC (€)</label>
                        <input type="number" class="form-control" id="e_ttc" name="cout_ttc" min="0" step="0.01" value="0">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="e_commentaire">Commentaire / référence facture</label>
                        <textarea class="form-control" id="e_commentaire" name="commentaire" rows="3"
                                  maxlength="2000"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>
