<?php
declare(strict_types=1);

/**
 * Liste du parc de véhicules avec filtres et pagination.
 *
 * @var array $vehicules, $filtres, $statuts, $entites, $loueurs, $lieux
 * @var string $base_url
 * @var int $total, $page, $nbPages, $parPage, $offset
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

$total = (int) ($total ?? count($vehicules));
$page = (int) ($page ?? 1);
$nbPages = (int) ($nbPages ?? 1);
$parPage = (int) ($parPage ?? 25);
$offset = (int) ($offset ?? 0);

$urlPage = static function (int $p) use ($base_url, $filtres, $parPage): string {
    $params = array_filter($filtres, static fn ($v) => $v !== '' && $v !== null);
    $params['page'] = $p;
    if ($parPage !== 25) {
        $params['par_page'] = $parPage;
    }
    return htmlspecialchars($base_url . '/vehicules?' . http_build_query($params), ENT_QUOTES, 'UTF-8');
};

$calculerPages = static function (int $actuelle, int $total): array {
    if ($total <= 7) {
        return range(1, $total);
    }
    if ($actuelle <= 4) {
        return [1, 2, 3, 4, 5, '...', $total];
    }
    if ($actuelle >= $total - 3) {
        return [1, '...', $total - 4, $total - 3, $total - 2, $total - 1, $total];
    }
    return [1, '...', $actuelle - 1, $actuelle, $actuelle + 1, '...', $total];
};
$pagesAffichees = $calculerPages($page, $nbPages);
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= $e($base_url . '/vehicules') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="1">
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

            <div class="col-12 mt-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <a href="<?= $e($base_url . '/vehicules?echeance=90') ?>" class="btn btn-sm btn-outline-orange">
                        Échéances sous 90 jours
                    </a>
                    <span class="ms-2 text-secondary small">
                        <?php if ($total > 0): ?>
                            Affichage de <strong><?= $offset + 1 ?></strong> à <strong><?= min($total, $offset + count($vehicules)) ?></strong> sur <strong><?= $total ?></strong> véhicule(s)
                        <?php else: ?>
                            0 véhicule
                        <?php endif; ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label small mb-0 text-secondary" for="par_page">Par page :</label>
                    <select class="form-select form-select-sm w-auto" id="par_page" name="par_page" onchange="this.form.submit()">
                        <?php foreach ([15, 25, 50, 100] as $n): ?>
                            <option value="<?= $n ?>" <?= $parPage === $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Parc de véhicules</h3>
        <?php if ($modifiable): ?>
            <div class="card-actions d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-import-csv">
                    <svg class="icon me-1" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                        <path d="M12 11v6" /><path d="M9 14l3 3l3 -3" />
                    </svg>
                    Import CSV
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-vehicule">
                    <svg class="icon me-1" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
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
                    <th>Durée</th>
                    <th>Km maxi</th>
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
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($v['marque_logo'])): ?>
                                    <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $v['id']) ?>" class="flex-shrink-0" title="<?= $e($v['marque_nom']) ?>">
                                        <img src="<?= $e(\Core\Url::upload('marques/' . $v['marque_logo'])) ?>"
                                             alt="<?= $e($v['marque_nom']) ?>"
                                             class="rounded bg-white p-1 border shadow-xs"
                                             style="height: 38px; width: auto; max-width: 60px; object-fit: contain;">
                                    </a>
                                <?php endif; ?>
                                <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $v['id']) ?>" class="text-reset fw-bold text-nowrap">
                                    <?= $e($v['immatriculation']) ?>
                                </a>
                            </div>
                        </td>
                        <td class="text-secondary"><?= $e($v['marque_nom'] . ' ' . $v['modele_nom']) ?></td>
                        <td class="text-secondary"><?= $e($v['entite_nom']) ?></td>
                        <td class="text-secondary"><?= $e($v['loueur_nom']) ?></td>
                        <td class="text-secondary"><?= $e($v['lieu_nom']) ?></td>
                        <td class="text-secondary"><?= $e((string) $v['date_entree']) ?></td>
                        <td class="text-secondary"><?= $e((string) $v['date_sortie_prevue']) ?></td>
                        <?php
                        $dureeContrat = $v['duree_contrat'] !== null ? (string) $v['duree_contrat'] . ' mois' : '—';
                        $kmMaxi = $v['km_maxi'] !== null ? number_format($v['km_maxi'], 0, ',', ' ') . ' km' : '—';
                        ?>
                        <td class="text-secondary"><?php echo $e($dureeContrat); ?></td>
                        <td class="text-secondary"><?php echo $e($kmMaxi); ?></td>
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
    <?php if ($total > 0): ?>
        <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
            <p class="m-0 text-secondary small">
                Affichage de <strong><?= $offset + 1 ?></strong> à <strong><?= min($total, $offset + count($vehicules)) ?></strong> sur <strong><?= $total ?></strong> véhicule<?= $total > 1 ? 's' : '' ?>
            </p>
            <?php if ($nbPages > 1): ?>
                <ul class="pagination m-0 ms-auto">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $urlPage(max(1, $page - 1)) ?>" tabindex="-1" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">
                            <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M15 6l-6 6l6 6"/>
                            </svg>
                            <span class="visually-hidden">Précédent</span>
                        </a>
                    </li>
                    <?php foreach ($pagesAffichees as $p): ?>
                        <?php if ($p === '...'): ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php elseif ($p === $page): ?>
                            <li class="page-item active"><span class="page-link"><?= $p ?></span></li>
                        <?php else: ?>
                            <li class="page-item"><a class="page-link" href="<?= $urlPage((int) $p) ?>"><?= $p ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <li class="page-item <?= $page >= $nbPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $urlPage(min($nbPages, $page + 1)) ?>" aria-disabled="<?= $page >= $nbPages ? 'true' : 'false' ?>">
                            <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 6l6 6l-6 6"/>
                            </svg>
                            <span class="visually-hidden">Suivant</span>
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($modifiable): ?>
    <?php
    $nomenclature = [
        'modeles' => \Models\Dictionary::list('modeles'),
        'entites' => \Models\Dictionary::list('entites'),
        'loueurs' => \Models\Dictionary::list('loueurs'),
        'lieux'   => \Models\Dictionary::list('lieux'),
    ];
    $this->partial('vehicles/_formulaire', [
        'base_url'     => $base_url,
        'vehicule'     => null,
        'nomenclature' => $nomenclature,
        'statuts'      => $statuts,
    ]);
    $this->partial('vehicles/_import_csv', [
        'base_url'     => $base_url,
        'nomenclature' => $nomenclature,
    ]);
    ?>
<?php endif; ?>
