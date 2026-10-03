<?php
declare(strict_types=1);

/**
 * Modale de saisie d'un véhicule (création et édition).
 *
 * @var ?array $vehicule
 * @var array $nomenclature, $statuts
 * @var string $base_url
 */

use Core\Csrf;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$v = static fn (string $cle, mixed $defaut = ''): string => htmlspecialchars((string) ($vehicule[$cle] ?? $defaut), ENT_QUOTES, 'UTF-8');
$id = (int) ($vehicule['id'] ?? 0);
?>
<div class="modal modal-blur fade" id="modal-vehicule" tabindex="-1" role="dialog" aria-hidden="true" aria-labelledby="titre-vehicule">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <form class="modal-content" method="post" action="<?= $e($base_url . '/vehicules/enregistrer') ?>"
              data-valide-vehicule="0" id="formulaire-vehicule">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="modal-header">
                <h5 class="modal-title" id="titre-vehicule"><?= $id > 0 ? 'Modifier le véhicule' : 'Ajouter un véhicule' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="v_immat">Immatriculation</label>
                        <input type="text" class="form-control" id="v_immat" name="immatriculation" required
                               maxlength="20" value="<?= $v('immatriculation') ?>" placeholder="AB-123-CD">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="v_modele">Marque &amp; modèle</label>
                        <select class="form-select" id="v_modele" name="modele_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($nomenclature['modeles'] as $m): ?>
                                <option value="<?= (int) $m['id'] ?>" <?= (int) ($vehicule['modele_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                                    <?= $e(($m['marque_libelle'] ?? '') !== '' ? $m['marque_libelle'] . ' ' . $m['nom'] : $m['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="v_entite">Entité propriétaire</label>
                        <select class="form-select" id="v_entite" name="entite_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($nomenclature['entites'] as $x): ?>
                                <option value="<?= (int) $x['id'] ?>" <?= (int) ($vehicule['entite_id'] ?? 0) === (int) $x['id'] ? 'selected' : '' ?>>
                                    <?= $e($x['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="v_loueur">Organisme loueur</label>
                        <select class="form-select" id="v_loueur" name="loueur_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($nomenclature['loueurs'] as $x): ?>
                                <option value="<?= (int) $x['id'] ?>" <?= (int) ($vehicule['loueur_id'] ?? 0) === (int) $x['id'] ? 'selected' : '' ?>>
                                    <?= $e($x['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="v_lieu">Lieu d'exploitation</label>
                        <select class="form-select" id="v_lieu" name="lieu_id" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($nomenclature['lieux'] as $x): ?>
                                <option value="<?= (int) $x['id'] ?>" <?= (int) ($vehicule['lieu_id'] ?? 0) === (int) $x['id'] ? 'selected' : '' ?>>
                                    <?= $e($x['nom'] . ($x['ville'] !== null ? ' (' . $x['ville'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="v_statut">Statut d'exploitation</label>
                        <select class="form-select" id="v_statut" name="statut">
                            <?php foreach ($statuts as $cle => $libelle): ?>
                                <option value="<?= $e($cle) ?>" <?= ($vehicule['statut'] ?? 'actif') === $cle ? 'selected' : '' ?>>
                                    <?= $e($libelle) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="v_entree">Date d'entrée en flotte</label>
                        <input type="date" class="form-control" id="v_entree" name="date_entree" required
                               value="<?= $v('date_entree') ?>">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label required" for="v_sortie">Date de sortie prévisionnelle</label>
                        <input type="date" class="form-control" id="v_sortie" name="date_sortie_prevue" required
                               value="<?= $v('date_sortie_prevue') ?>">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="v_sortie_eff">Date de sortie effective</label>
                        <input type="date" class="form-control" id="v_sortie_eff" name="date_sortie_effective"
                               value="<?= $v('date_sortie_effective') ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="immatricule" value="1" id="v_immatricule"
                                   <?= (int) ($vehicule['immatricule'] ?? 0) === 1 ? 'checked' : '' ?>>
                            <span class="form-check-label">Véhicule non immatriculé (usage interne)</span>
                        </label>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="v_commentaire">Commentaire</label>
                        <textarea class="form-control" id="v_commentaire" name="commentaire" rows="3"
                                  maxlength="2000"><?= $v('commentaire') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" id="valider-vehicule">
                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12l5 5l10 -10"/>
                    </svg>
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
