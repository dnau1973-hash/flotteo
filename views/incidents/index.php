<?php
declare(strict_types=1);

/**
 * Liste des incidents et sinistres.
 *
 * @var array $incidents, $filtres, $vehicules, $types, $statuts
 * @var string $base_url
 */

use Core\Auth;
use Core\Csrf;
use Core\Request;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$modifiable = Auth::can(Auth::ROLE_MODIFICATION);
?>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= $e($base_url . '/incidents') ?>" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="i_vehicule">Véhicule</label>
                <select class="form-select" id="i_vehicule" name="vehicule_id">
                    <option value="">Tous les véhicules</option>
                    <?php foreach ($vehicules as $v): ?>
                        <option value="<?= (int) $v['id'] ?>" <?= (int) $filtres['vehicule_id'] === (int) $v['id'] ? 'selected' : '' ?>>
                            <?= $e($v['immatriculation'] . ' — ' . $v['marque_nom'] . ' ' . $v['modele_nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="i_type">Type</label>
                <select class="form-select" id="i_type" name="type">
                    <option value="">Tous</option>
                    <?php foreach ($types as $cle => $libelle): ?>
                        <option value="<?= $e($cle) ?>" <?= $filtres['type'] === $cle ? 'selected' : '' ?>><?= $e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="i_statut">Statut</label>
                <select class="form-select" id="i_statut" name="statut">
                    <option value="">Tous</option>
                    <?php foreach ($statuts as $cle => $libelle): ?>
                        <option value="<?= $e($cle) ?>" <?= $filtres['statut'] === $cle ? 'selected' : '' ?>><?= $e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="i_du">Du</label>
                <input type="date" class="form-control" id="i_du" name="du" value="<?= $e($filtres['du']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="i_au">Au</label>
                <input type="date" class="form-control" id="i_au" name="au" value="<?= $e($filtres['au']) ?>">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <a href="<?= $e($base_url . '/incidents') ?>" class="btn btn-outline-secondary">Réinitialiser</a>
                <span class="ms-2 text-secondary small align-self-center"><?= count($incidents) ?> incident(s)</span>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Incidents &amp; sinistres</h3>
        <?php if ($modifiable): ?>
            <div class="card-actions">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-incident">
                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 5v14"/><path d="M5 12h14"/>
                    </svg>
                    Signaler un incident
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <?php if ($incidents === []): ?>
            <div class="empty-state">Aucun incident ne correspond aux critères sélectionnés.</div>
        <?php else: ?>
            <table class="table table-vcenter card-table table-hover">
                <thead>
                <tr>
                    <th>Date</th><th>Véhicule</th><th>Type</th><th>Lieu</th>
                    <th>Responsable</th><th>Immatr.</th><th>Statut</th><th class="w-1">Pièces</th><th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($incidents as $i): ?>
                    <?php $nbFichiers = count(\Models\Incident::files((int) $i['id'])); ?>
                    <tr>
                        <td class="text-nowrap"><?= $e((string) $i['date_incident']) ?></td>
                        <td>
                            <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $i['vehicule_id']) ?>" class="text-reset fw-bold">
                                <?= $e($i['immatriculation']) ?>
                            </a>
                            <div class="small text-secondary"><?= $e($i['marque_nom'] . ' ' . $i['modele_nom']) ?></div>
                        </td>
                        <td><?= $e(\Models\Incident::TYPES[(string) $i['type']] ?? (string) $i['type']) ?></td>
                        <td class="text-secondary"><?= $e((string) ($i['lieu'] ?: '—')) ?></td>
                        <td>
                            <span class="badge <?= (int) $i['responsable'] === 1 ? 'bg-red-lt' : 'bg-secondary-lt' ?>">
                                <?= (int) $i['responsable'] === 1 ? 'Responsable' : 'Non responsable' ?>
                            </span>
                        </td>
                        <td class="text-center"><?= (int) $i['immatricule'] === 1 ? 'Oui' : 'Non' ?></td>
                        <td>
                            <span class="badge <?= $i['statut'] === 'cloture' ? 'bg-green-lt' : ($i['statut'] === 'en_traitement' ? 'bg-orange-lt' : 'bg-red-lt') ?> badge-statut">
                                <?= $e(\Models\Incident::STATUTS[(string) $i['statut']] ?? (string) $i['statut']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($nbFichiers > 0): ?>
                                <span class="badge bg-azure-lt"><?= $nbFichiers ?></span>
                            <?php else: ?>
                                <span class="text-secondary small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-actions text-end">
                            <a href="<?= $e($base_url . '/incidents/voir?id=' . (int) $i['id']) ?>" class="btn btn-sm">Dossier</a>
                            <?php if ($modifiable): ?>
                                <form method="post" action="<?= $e($base_url . '/incidents/supprimer') ?>" class="d-inline"
                                      data-confirmer="Supprimer cet incident et toutes ses pièces jointes ?">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
                                    <button type="submit" class="btn btn-sm text-danger" title="Supprimer">
                                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M4 7h16"/><path d="M9 7v-3h6v3"/><path d="M6 7l1 13h10l1 -13"/>
                                        </svg>
                                    </button>
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
    <?php $this->partial('incidents/_formulaire', [
        'base_url'   => $base_url,
        'vehicules'  => $vehicules,
        'types'      => $types,
        'statuts'    => $statuts,
        'vehicule_id' => (int) $filtres['vehicule_id'],
    ]); ?>
<?php endif; ?>
