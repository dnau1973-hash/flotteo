<?php
declare(strict_types=1);

/**
 * Tableau de bord : cartes KPI et graphiques de pilotage.
 *
 * @var array $stats, $couts, $echeances, $immobilises, $entites, $loueurs
 * @var ?int $entite_id
 * @var string $base_url
 */

use Models\Vehicle;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$formater = static fn (float $v): string => number_format($v, 0, ',', ' ') . ' €';
$icon = static function (string $name): string {
    $p = [
        'car'   => '<path d="M5 17h-2v-5l2 -5h14l2 5v5h-2"/><path d="M5 12h14"/><circle cx="7" cy="14" r="1"/><circle cx="17" cy="14" r="1"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0 5 5l-9.4 9.4a2.1 2.1 0 0 1 -3 -3z"/>',
        'alert' => '<path d="M12 9v4"/><path d="M10.3 3.9l-8 14a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7 -3l-8 -14a2 2 0 0 0 -3.4 0z"/><path d="M12 17h.01"/>',
        'euro'  => '<path d="M17 5a7 7 0 1 0 0 14"/><path d="M4 10h7"/><path d="M4 14h6"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($p[$name] ?? $p['car']) . '</svg>';
};

$entiteActive = null;
if (!empty($entite_id)) {
    foreach ($entites as $ent) {
        if ((int) $ent['id'] === (int) $entite_id) {
            $entiteActive = $ent;
            break;
        }
    }
}
usort($entites, static fn (array $a, array $b): int => strcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? '')));
?>

<!-- ============================ En-tête & Filtre Entité ============================ -->
<div class="row align-items-center justify-content-between g-2 mb-3">
    <div class="col-12 col-md-auto">
        <div class="d-flex align-items-center gap-2">
            <h2 class="page-title mb-0">Tableau de bord</h2>
            <?php if ($entiteActive !== null): ?>
                <span class="badge bg-blue-lt font-monospace">
                    <?= $e($entiteActive['nom']) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="text-secondary small">Synthèse opérationnelle, financière et suivi des risques</div>
    </div>
    <div class="col-12 col-md-auto">
        <form method="get" action="<?= $e($base_url . '/dashboard') ?>" class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white text-secondary">
                    <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 21l18 0"/><path d="M9 8l1 0"/><path d="M9 12l1 0"/><path d="M9 16l1 0"/><path d="M14 8l1 0"/><path d="M14 12l1 0"/><path d="M14 16l1 0"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16"/>
                    </svg>
                    <span class="ms-1 fw-medium">Entité</span>
                </span>
                <select class="form-select form-select-sm" id="filtre_entite" name="entite" onchange="this.form.submit()" style="min-width: 200px;">
                    <option value="">Toutes les entités</option>
                    <?php foreach ($entites as $ent): ?>
                        <option value="<?= (int) $ent['id'] ?>" <?= $entite_id === (int) $ent['id'] ? 'selected' : '' ?>>
                            <?= $e($ent['nom']) ?><?= !empty($ent['code']) ? ' (' . $e($ent['code']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($entite_id !== null): ?>
                    <a href="<?= $e($base_url . '/dashboard') ?>" class="btn btn-sm btn-outline-secondary" title="Réinitialiser le filtre entité">
                        <svg class="icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 6l-12 12"/><path d="M6 6l12 12"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ============================ Cartes KPI ============================ -->
<div class="row row-deck row-cards mb-3">
    <div class="col-6 col-sm-6 col-lg-3">
        <div class="card card-sm kpi-card h-100 d-flex flex-column">
            <div class="card-body">
                <div class="kpi-icone"><?= $icon('car') ?></div>
                <div class="kpi-libelle">Flotte active</div>
                <div class="kpi-valeur"><?= (int) $stats['total'] ?></div>
                <div class="kpi-detail mt-1">
                    <?= (int) $stats['actifs'] ?> en exploitation &middot; <?= (int) $stats['sortis'] ?> sorti(s)
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-sm-6 col-lg-3">
        <div class="card card-sm kpi-card h-100 d-flex flex-column">
            <div class="card-body">
                <div class="kpi-icone" style="color:#d63939;background:rgba(214,57,57,.08)"><?= $icon('wrench') ?></div>
                <div class="kpi-libelle">Taux d'immobilisation</div>
                <div class="kpi-valeur"><?= number_format((float) $stats['taux_immobilisation'], 1, ',', '') ?> %</div>
                <div class="jauge mt-2">
                    <span style="width:<?= (float) $stats['taux_immobilisation'] ?>%;background:#d63939"></span>
                </div>
                <div class="kpi-detail mt-1"><?= (int) $stats['immobilises'] ?> véhicule(s) indisponible(s)</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-sm-6 col-lg-3">
        <div class="card card-sm kpi-card h-100 d-flex flex-column">
            <div class="card-body">
                <div class="kpi-icone" style="color:#f76707;background:rgba(247,103,7,.09)"><?= $icon('alert') ?></div>
                <div class="kpi-libelle">Échéances &lt; 90 jours</div>
                <div class="kpi-valeur"><?= (int) $stats['echeances_90j'] ?></div>
                <div class="kpi-detail mt-1">
                    <a href="<?= $e($base_url . '/vehicules?echeance=90' . ($entite_id ? '&entite=' . (int) $entite_id : '')) ?>" class="link-primary">Consulter les sorties</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-sm-6 col-lg-3">
        <div class="card card-sm kpi-card h-100 d-flex flex-column">
            <div class="card-body">
                <div class="kpi-icone" style="color:#2fb344;background:rgba(47,179,68,.09)"><?= $icon('euro') ?></div>
                <div class="kpi-libelle">Entretien du mois</div>
                <div class="kpi-valeur"><?= $e($formater((float) $couts['mois'])) ?></div>
                <div class="kpi-detail mt-1">Cumul annuel : <?= $e($formater((float) $couts['annee'])) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ============================ Graphiques ============================ -->
<div class="row row-cards mb-3">
    <div class="col-lg-8">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Échéancier des sorties de flotte — 12 mois</h3>
                <div class="card-actions text-secondary small">Volumes mensuels de restitution aux loueurs</div>
            </div>
            <div class="card-body flex-fill">
                <div id="graph-echeancier" class="chart-bars"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title" id="titre-repartition">Répartition par entité propriétaire</h3>
                <div class="card-actions">
                    <ul class="nav nav-pills onglet-repartition">
                        <li class="nav-item"><a class="nav-link active" href="#" data-dimension="entite">Entités</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" data-dimension="loueur">Loueurs</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" data-dimension="lieu">Lieux</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body flex-fill">
                <div id="graph-repartition" class="chart-cercle"></div>
            </div>
        </div>
    </div>
</div>

<div class="row row-cards mb-3">
    <div class="col-lg-7">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Évolution des dépenses d'entretien par typologie</h3>
                <div class="card-actions text-secondary small">Montants HT, 12 derniers mois</div>
            </div>
            <div class="card-body flex-fill">
                <div id="graph-entretien" class="chart-bars"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Coût d'entretien moyen par modèle</h3>
                <div class="card-actions text-secondary small">TCO par véhicule</div>
            </div>
            <div class="card-body flex-fill">
                <div id="graph-tco" class="chart-haut"></div>
            </div>
        </div>
    </div>
</div>

<div class="row row-cards">
    <div class="col-lg-5">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Matrice des incidents</h3>
                <div class="card-actions text-secondary small">Répartition par type</div>
            </div>
            <div class="card-body flex-fill">
                <div id="graph-incidents-types" class="chart-incidents"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Tendance de la sinistralité</h3>
                <div class="card-actions text-secondary small">12 derniers mois</div>
            </div>
            <div class="card-body flex-fill">
                <div id="graph-incidents" class="chart-incidents"></div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== Listes d'action rapide ==================== -->
<div class="row row-cards mt-3">
    <div class="col-lg-6">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Sorties de flotte les plus proches</h3>
                <div class="card-actions">
                    <a href="<?= $e($base_url . '/vehicules?echeance=90' . ($entite_id ? '&entite=' . (int) $entite_id : '')) ?>" class="btn btn-sm">Tout voir</a>
                </div>
            </div>
            <div class="table-responsive flex-fill">
                <?php if ($echeances === []): ?>
                    <div class="empty-state">Aucune sortie prévue dans les 90 prochains jours.</div>
                <?php else: ?>
                    <table class="table table-vcenter card-table">
                        <thead><tr><th class="col-immat">Immat.</th><th>Véhicule</th><th>Loueur</th><th class="w-1">Reste</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($echeances, 0, 8) as $v): ?>
                            <?php $jours = Vehicle::joursRestants($v); ?>
                            <tr>
                                <td class="col-immat">
                                    <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $v['id']) ?>" class="text-reset fw-bold">
                                        <?= $e($v['immatriculation']) ?>
                                    </a>
                                </td>
                                <td class="text-secondary"><?= $e($v['marque_nom'] . ' ' . $v['modele_nom']) ?></td>
                                <td class="text-secondary"><?= $e($v['loueur_nom']) ?></td>
                                <td>
                                    <span class="badge <?= $jours !== null && $jours <= 30 ? 'bg-red-lt' : 'bg-orange-lt' ?>">
                                        J-<?= (int) $jours ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Véhicules immobilisés</h3>
                <div class="card-actions">
                    <a href="<?= $e($base_url . '/vehicules?statut=immobilise' . ($entite_id ? '&entite=' . (int) $entite_id : '')) ?>" class="btn btn-sm">Tout voir</a>
                </div>
            </div>
            <div class="table-responsive flex-fill">
                <?php if ($immobilises === []): ?>
                    <div class="empty-state">Aucun véhicule immobilisé. La flotte est pleinement disponible.</div>
                <?php else: ?>
                    <table class="table table-vcenter card-table">
                        <thead><tr><th class="col-immat">Immat.</th><th>Véhicule</th><th>Lieu</th><th>Statut</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($immobilises, 0, 8) as $v): ?>
                            <tr>
                                <td class="col-immat">
                                    <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $v['id']) ?>" class="text-reset fw-bold">
                                        <?= $e($v['immatriculation']) ?>
                                    </a>
                                </td>
                                <td class="text-secondary"><?= $e($v['marque_nom'] . ' ' . $v['modele_nom']) ?></td>
                                <td class="text-secondary"><?= $e($v['lieu_nom']) ?></td>
                                <td><span class="badge bg-red-lt badge-statut">Immobilisé</span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php $this->useScript('dashboard.js'); ?>
