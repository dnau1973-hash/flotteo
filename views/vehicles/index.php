<?php
declare(strict_types=1);

/**
 * Liste du parc de véhicules avec filtres.
 *
 * @var array $vehicules, $filtres, $statuts, $entites, $loueurs, $lieux
 * @var string $base_url
 */

use Core\Auth;
use Core\Csrf;
use Models\Vehicle;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$modifiable = Auth::can(Auth::ROLE_MODIFICATION);
$badge = static function (string $statut): string {
    $classes = ['actif' => 'bg-green-lt', 'immobilise' => 'bg-red-lt', 'sorti' => 'bg-secondary-lt'];
    $libelles = Vehicle::STATUTS;
    return '<span class="badge ' . ($classes[$statut] ?? 'bg-secondary-lt') . ' badge-statut">'
        . htmlspecialchars($libelles[$statut] ?? $statut, ENT_QUOTES, 'UTF-8') . '</span>';
};
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= $e($base_url . '/vehicules') ?>" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="q">Recherche</label>
                <input type="search" class="form-control" id="q" name="q" placeholder="Immatriculation, marque, modèle"
                       value="<?= $e($filtres['q']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="entite">Entité</label>
                <select class="form-select" id="entite" name="entite">
                    <option value="">Toutes</option>
                    <?php foreach ($entites as $ent): ?>
                        <option value="<?= (int) $ent['id'] ?>" <?= (int) $filtres['entite'] === (int) $ent['id'] ? 'selected' : '' ?>>
                            <?= $e($ent['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="loueur">Loueur</label>
                    <select class="form-select" id="loueur" name="loueur" autocomplete="off">
                    <option value="">Tous</option>
                    <?php foreach ($loueurs as $l): ?>
                        <option value="<?= (int) $l['id'] ?>" <?= (int) $filtres['loueur'] === (int) $l['id'] ? 'selected' : '' ?>>
                            <?= $e($l['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="lieu">Lieu</label>
                <select class="form-select" id="lieu" name="lieu">
                    <option value="">Tous</option>
                    <?php foreach ($lieux as $l): ?>
                        <option value="<?= (int) $l['id'] ?>" <?= (int) $filtres['lieu'] === (int) $l['id'] ? 'selected' : '' ?>>
                            <?= $e($l['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="statut">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="">Tous</option>
                    <?php foreach ($statuts as $cle => $libelle): ?>
                        <option value="<?= $e($cle) ?>" <?= $filtres['statut'] === $cle ? 'selected' : '' ?>><?= $e($libelle) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill" title="Filtrer">
                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/>
                    </svg>
                </button>
                <a href="<?= $e($base_url . '/vehicules') ?>" class="btn btn-outline-secondary" title="Réinitialiser">✕</a>
            </div>

            <div class="col-12 mt-2">
                <a href="<?= $e($base_url . '/vehicules?echeance=90') ?>" class="btn btn-sm btn-outline-orange">
                    Échéances sous 90 jours
                </a>
                <span class="ms-2 text-secondary small"><?= count($vehicules) ?> véhicule(s) affiché(s)</span>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Parc de véhicules</h3>
        <?php if ($modifiable): ?>
            <div class="card-actions">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-vehicule">
                    <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 5v14"/><path d="M5 12h14"/>
                    </svg>
                    Ajouter un véhicule
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <?php if ($vehicules === []): ?>
            <div class="empty-state">
                <svg class="icon" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor"
                     stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 17h-2v-5l2 -5h14l2 5v5h-2"/><path d="M5 12h14"/>
                    <circle cx="7" cy="14" r="1"/><circle cx="17" cy="14" r="1"/>
                </svg>
                <p class="mb-0">Aucun véhicule ne correspond aux critères sélectionnés.</p>
            </div>
        <?php else: ?>
            <table class="table table-vcenter card-table table-hover">
                <thead>
                <tr>
                    <th>Immatriculation</th>
                    <th>Véhicule</th>
                    <th>Entité</th>
                    <th>Loueur</th>
                    <th>Lieu</th>
                    <th>Entrée</th>
                    <th>Sortie prévue</th>
                    <th>Échéance</th>
                    <th>Statut</th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($vehicules as $v): ?>
                    <?php $jours = Vehicle::joursRestants($v); ?>
                    <tr>
                        <td>
                            <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $v['id']) ?>" class="text-reset fw-bold">
                                <?= $e($v['immatriculation']) ?>
                            </a>
                        </td>
                        <td class="text-secondary"><?= $e($v['marque_nom'] . ' ' . $v['modele_nom']) ?></td>
                        <td class="text-secondary"><?= $e($v['entite_nom']) ?></td>
                        <td class="text-secondary"><?= $e($v['loueur_nom']) ?></td>
                        <td class="text-secondary"><?= $e($v['lieu_nom']) ?></td>
                        <td class="text-secondary"><?= $e((string) $v['date_entree']) ?></td>
                        <td class="text-secondary"><?= $e((string) $v['date_sortie_prevue']) ?></td>
                        <td>
                            <?php if ($jours === null): ?>
                                <span class="badge bg-secondary-lt">Restitué</span>
                            <?php elseif ($jours < 0): ?>
                                <span class="badge bg-red-lt">Retard <?= abs($jours) ?> j</span>
                            <?php elseif ($jours <= 90): ?>
                                <span class="badge bg-orange-lt">J-<?= (int) $jours ?></span>
                            <?php else: ?>
                                <span class="text-secondary small">J-<?= (int) $jours ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $badge((string) $v['statut']) ?></td>
                        <td class="cell-actions text-end">
                            <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $v['id']) ?>"
                               class="btn btn-sm" title="Consulter la fiche">
                                <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10 12a2 2 0 1 0 0 -4a2 2 0 0 0 0 4z"/>
                                    <path d="M21 12c-2.5 -4.5 -6.5 -7 -11 -7s-8.5 2.5 -11 7c2.5 4.5 6.5 7 11 7s8.5 -2.5 11 -7z"/>
                                </svg>
                            </a>
                            <?php if ($modifiable): ?>
                                <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $v['id'] . '&modifier=1') ?>"
                                   class="btn btn-sm" title="Modifier">
                                    <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 20h4l10 -10l-4 -4l-10 10z"/><path d="M13.5 6.5l4 4"/>
                                    </svg>
                                </a>
                                <button class="btn btn-sm text-danger" type="button"
                                        data-supprimer="form-suppression-<?= (int) $v['id'] ?>"
                                        title="Supprimer">
                                    <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 7h16"/><path d="M9 7v-3h6v3"/><path d="M6 7l1 13h10l1 -13"/>
                                    </svg>
                                </button>
                                <form id="form-suppression-<?= (int) $v['id'] ?>" method="post"
                                      action="<?= $e($base_url . '/vehicules/supprimer') ?>" class="d-none"
                                      data-confirmer="Supprimer définitivement le véhicule <?= $e($v['immatriculation']) ?> ? Cette action est irréversible.">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
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
    <?php
    $this->partial('vehicles/_formulaire', [
        'base_url'   => $base_url,
        'vehicule'   => null,
        'nomenclature' => [
            'modeles' => \Models\Dictionary::list('modeles'),
            'entites' => \Models\Dictionary::list('entites'),
            'loueurs' => \Models\Dictionary::list('loueurs'),
            'lieux'   => \Models\Dictionary::list('lieux'),
        ],
        'statuts'    => $statuts,
    ]);
    ?>
<?php endif; ?>
