<?php
declare(strict_types=1);

/**
 * Échéancier des fins de contrat.
 *
 * Bloc autonome, précédemment affiché dans les paramètres. Les véhicules sont
 * triés par urgence croissante — le plus proche d'échéance en tête — et non par
 * palier : c'est l'ordre qui sert à décider quoi traiter en premier.
 *
 * Le filtrage se fait sans JavaScript, par liens : chaque palier est une URL
 * porteuse d'un paramètre `palier`, l'état courant est signalé par `aria-current`
 * et par la classe `active`, comme le reste de la barre.
 *
 * @var array $echeances, $parPalier
 * @var array{int,int,int} $paliers
 * @var int $sous30, $sous90
 * @var array<string,mixed>|null $prochain
 * @var string $base_url
 */

use Core\Icon;
use Core\Request;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

/** Palier demandé ; 0 = aucun filtre. */
$palierActif = (int) Request::int('palier');

/** Seuil de la pastille « reste » : le rouge est réservé à l'immédiat. */
$couleur = static function (int $jours): string {
    return match (true) {
        $jours <= 30  => 'bg-red-lt',
        $jours <= 90  => 'bg-orange-lt',
        $jours <= 180 => 'bg-yellow-lt',
        default       => 'bg-azure-lt',
    };
};

$affiches = $palierActif === 0
    ? $echeances
    : array_values(array_filter($echeances, static fn (array $v): bool => (int) $v['palier'] === $palierActif));

$libellesPaliers = [];
foreach ($paliers as $p) {
    $libellesPaliers[$p] = $p >= 60 ? 'Sous ' . (int) round($p / 30) . ' mois' : 'Sous 1 mois';
}
?>

<div class="row row-deck row-cards">

    <?php if ($echeances !== []): ?>
        <!-- ============ Synthèse ============ -->
        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader">Échéances surveillées</div>
                    </div>
                    <div class="h1 mb-0 mt-2 kpi-valeur"><?= count($echeances) ?></div>
                    <div class="kpi-detail">
                        véhicules encore en service dans les <?= $e((string) max($paliers)) ?> jours
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center text-danger">
                        <div class="subheader"><?= $icone('triangle-exclamation', 'me-1') ?>Sous 30 jours</div>
                    </div>
                    <div class="h1 mb-0 mt-2 kpi-valeur text-danger"><?= $sous30 ?></div>
                    <div class="kpi-detail">échéance la plus proche : action requise</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center text-orange">
                        <div class="subheader"><?= $icone('bell', 'me-1') ?>Sous 90 jours</div>
                    </div>
                    <div class="h1 mb-0 mt-2 kpi-valeur text-orange"><?= $sous90 ?></div>
                    <div class="kpi-detail">prévoir le renouvellement ou la restitution</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="subheader"><?= $icone('calendar-days', 'me-1') ?>Prochaine échéance</div>
                    </div>
                    <?php if ($prochain !== null): ?>
                        <div class="h1 mb-0 mt-2 kpi-valeur">J-<?= (int) $prochain['jours_restants'] ?></div>
                        <div class="kpi-detail text-truncate">
                            <?= $e($prochain['immatriculation']) ?> — <?= $e($prochain['date_sortie_prevue']) ?>
                        </div>
                    <?php else: ?>
                        <div class="h1 mb-0 mt-2 kpi-valeur">—</div>
                        <div class="kpi-detail">aucune échéance à venir</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============ Échéancier ============ -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= $icone('calendar-days', 'me-2') ?>Échéancier des fins de contrat</h3>
                <div class="card-actions">
                    <div class="btn-group" role="group" aria-label="Filtrer par palier">
                        <a class="btn btn-sm <?= $palierActif === 0 ? 'btn-primary' : '' ?>"
                           href="<?= $e($base_url . '/echeances') ?>"
                           <?= $palierActif === 0 ? 'aria-current="page"' : '' ?>>
                            Tous <span class="badge bg-white-lt ms-1"><?= count($echeances) ?></span>
                        </a>
                        <?php foreach ($paliers as $p): ?>
                            <a class="btn btn-sm <?= $palierActif === $p ? 'btn-primary' : '' ?>"
                               href="<?= $e($base_url . '/echeances?palier=' . $p) ?>"
                               <?= $palierActif === $p ? 'aria-current="page"' : '' ?>>
                                <?= $e($libellesPaliers[$p]) ?>
                                <span class="badge bg-white-lt ms-1"><?= (int) ($parPalier[$p] ?? 0) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($echeances === []): ?>
                <div class="empty-state">
                    Aucun véhicule n'arrive à échéance dans les paliers configurés.
                </div>
            <?php elseif ($affiches === []): ?>
                <div class="empty-state">Aucun véhicule dans ce palier.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-hover">
                        <thead>
                        <tr>
                            <th class="col-immat">Immat.</th>
                            <th>Véhicule</th>
                            <th>Entité</th>
                            <th>Loueur</th>
                            <th>Lieu</th>
                            <th class="w-1">Sortie prévue</th>
                            <th>Palier</th>
                            <th class="w-1 text-end">Reste</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($affiches as $v): ?>
                            <?php $reste = (int) $v['jours_restants']; ?>
                            <tr>
                                <td class="fw-bold col-immat">
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($v['marque_logo'])): ?>
                                            <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $v['id']) ?>" class="flex-shrink-0" title="<?= $e($v['marque_nom']) ?>">
                                                <img src="<?= $e(\Core\Url::upload('marques/' . $v['marque_logo'])) ?>"
                                                     alt="<?= $e($v['marque_nom']) ?>"
                                                     class="rounded bg-white p-1 border shadow-xs"
                                                     style="height: 34px; width: auto; max-width: 52px; object-fit: contain;">
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $v['id']) ?>" class="text-reset text-nowrap">
                                            <?= $e($v['immatriculation']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td><?= $e($v['marque_nom'] . ' ' . $v['modele_nom']) ?></td>
                                <td class="text-secondary"><?= $e($v['entite_nom']) ?></td>
                                <td class="text-secondary"><?= $e($v['loueur_nom']) ?></td>
                                <td class="text-secondary"><?= $e($v['lieu_nom'] ?? '—') ?></td>
                                <td class="text-secondary"><?= $e((string) $v['date_sortie_prevue']) ?></td>
                                <td><span class="badge bg-blue-lt"><?= $e($v['palier_label']) ?></span></td>
                                <td class="text-end">
                                    <span class="badge <?= $couleur($reste) ?>">J-<?= $reste ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>