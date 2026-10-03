<?php
declare(strict_types=1);

/**
 * Historique d'entretien : filtres, cumul et saisie des prestations.
 *
 * @var array $entretiens, $filtres, $vehicules, $types, $categories
 * @var float $total_ht
 * @var string $base_url
 */

use Core\Auth;
use Core\Csrf;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$modifiable = Auth::can(Auth::ROLE_MODIFICATION);
?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= $e($base_url . '/entretien') ?>" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="f_vehicule">Véhicule</label>
                <select class="form-select" id="f_vehicule" name="vehicule_id">
                    <option value="">Tous les véhicules</option>
                    <?php foreach ($vehicules as $v): ?>
                        <option value="<?= (int) $v['id'] ?>" <?= (int) $filtres['vehicule_id'] === (int) $v['id'] ? 'selected' : '' ?>>
                            <?= $e($v['immatriculation'] . ' — ' . $v['marque_nom'] . ' ' . $v['modele_nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="f_categorie">Catégorie</label>
                <select class="form-select" id="f_categorie" name="categorie">
                    <option value="">Toutes</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $e($c) ?>" <?= $filtres['categorie'] === $c ? 'selected' : '' ?>><?= $e(ucfirst($c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="f_du">Du</label>
                <input type="date" class="form-control" id="f_du" name="du" value="<?= $e($filtres['du']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="f_au">Au</label>
                <input type="date" class="form-control" id="f_au" name="au" value="<?= $e($filtres['au']) ?>">
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="<?= $e($base_url . '/entretien') ?>" class="btn btn-outline-secondary">Réinitialiser</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Révisions et prestations</h3>
        <div class="card-actions">
            <span class="badge bg-blue-lt">Cumul HT : <?= $e(number_format($total_ht, 2, ',', ' ')) ?> €</span>
            <?php if ($modifiable): ?>
                <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#modal-entretien">
                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 5v14"/><path d="M5 12h14"/>
                    </svg>
                    Nouvelle prestation
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-responsive">
        <?php if ($entretiens === []): ?>
            <div class="empty-state">Aucune prestation enregistrée pour ces critères.</div>
        <?php else: ?>
            <table class="table table-vcenter card-table table-hover">
                <thead>
                <tr>
                    <th>Date</th><th>Véhicule</th><th>Prestation</th><th>Catégorie</th>
                    <th>Kilométrage</th><th class="w-1">Coût HT</th><th class="w-1">Coût TTC</th><th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($entretiens as $m): ?>
                    <tr>
                        <td class="text-nowrap"><?= $e((string) $m['date_operation']) ?></td>
                        <td>
                            <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $m['vehicule_id']) ?>" class="text-reset fw-bold">
                                <?= $e($m['immatriculation']) ?>
                            </a>
                            <div class="small text-secondary"><?= $e($m['marque_nom'] . ' ' . $m['modele_nom']) ?></div>
                        </td>
                        <td><?= $e((string) $m['type_libelle']) ?></td>
                        <td><span class="badge bg-blue-lt"><?= $e(ucfirst((string) $m['type_categorie'])) ?></span></td>
                        <td class="text-secondary"><?= $e(number_format((int) $m['kilometrage'], 0, ',', ' ')) ?> km</td>
                        <td class="text-end"><?= $e(number_format((float) $m['cout_ht'], 2, ',', ' ')) ?> €</td>
                        <td class="text-end text-secondary"><?= $e(number_format((float) $m['cout_ttc'], 2, ',', ' ')) ?> €</td>
                        <td class="cell-actions text-end">
                            <?php if ($modifiable): ?>
                                <button class="btn btn-sm" data-edition-entretien
                                        data-id="<?= (int) $m['id'] ?>"
                                        data-vehicule="<?= (int) $m['vehicule_id'] ?>"
                                        data-type="<?= (int) $m['type_intervention_id'] ?>"
                                        data-date="<?= $e((string) $m['date_operation']) ?>"
                                        data-km="<?= (int) $m['kilometrage'] ?>"
                                        data-ht="<?= $e((string) $m['cout_ht']) ?>"
                                        data-ttc="<?= $e((string) $m['cout_ttc']) ?>"
                                        data-commentaire="<?= $e((string) $m['commentaire']) ?>"
                                        title="Modifier">
                                    <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 20h4l10 -10l-4 -4l-10 10z"/><path d="M13.5 6.5l4 4"/>
                                    </svg>
                                </button>
                                <button class="btn btn-sm text-danger" type="button"
                                        data-supprimer="form-suppr-entretien-<?= (int) $m['id'] ?>" title="Supprimer">
                                    <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 7h16"/><path d="M9 7v-3h6v3"/><path d="M6 7l1 13h10l1 -13"/>
                                    </svg>
                                </button>
                                <form id="form-suppr-entretien-<?= (int) $m['id'] ?>" method="post" class="d-none"
                                      action="<?= $e($base_url . '/entretien/supprimer') ?>"
                                      data-confirmer="Supprimer cette prestation d'entretien ?">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($modifiable): ?>
    <?php $this->partial('maintenance/_formulaire', [
        'base_url' => $base_url,
        'vehicules' => $vehicules,
        'types'     => $types,
        'vehicule_id' => (int) $filtres['vehicule_id'],
    ]); ?>
<?php endif; ?>

<?php $this->useScript('entretien.js'); ?>
