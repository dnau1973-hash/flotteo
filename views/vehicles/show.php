<?php
declare(strict_types=1);

/**
 * Fiche détaillée d'un véhicule : cycle d'exploitation, entretien, incidents.
 *
 * @var array $vehicule, $entretiens, $nomenclature, $statuts
 * @var string $base_url
 */

use Core\Auth;
use Core\Csrf;
use Core\Icon;
use Models\Incident;
use Models\Vehicle;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);
$modifiable = Auth::can(Auth::ROLE_MODIFICATION);
$jours = Vehicle::joursRestants($vehicule);
$coutTotal = array_sum(array_map(static fn (array $m): float => (float) $m['cout_ht'], $entretiens));
$dernierKm = (int) ($vehicule['kilometrage'] ?? 0);
foreach ($entretiens as $m) {
    $dernierKm = max($dernierKm, (int) $m['kilometrage']);
}

$classes = ['actif' => 'bg-green-lt', 'immobilise' => 'bg-red-lt', 'sorti' => 'bg-secondary-lt'];
$liens = [
    'Identifiant interne'   => '#' . (int) $vehicule['id'],
    'Immatriculation'       => (string) $vehicule['immatriculation'],
    'Marque'                => (string) $vehicule['marque_nom'],
    'Modèle'                => (string) $vehicule['modele_nom'],
    'Couleur'               => !empty($vehicule['couleur']) ? (string) $vehicule['couleur'] : '—',
    'Motorisation'          => !empty($vehicule['motorisation']) ? (Vehicle::MOTORISATIONS[$vehicule['motorisation']] ?? ucfirst((string) $vehicule['motorisation'])) : '—',
    'Type de boîte'         => !empty($vehicule['type_boite']) ? (Vehicle::TYPES_BOITE[$vehicule['type_boite']] ?? ucfirst((string) $vehicule['type_boite'])) : 'Mécanique',
    'Entité propriétaire'   => (string) $vehicule['entite_nom'] . ' (' . (string) $vehicule['entite_code'] . ')',
    'Organisme loueur'      => (string) $vehicule['loueur_nom'],
    "Lieu d'exploitation"   => (string) $vehicule['lieu_nom'] . ($vehicule['lieu_ville'] !== null ? ' — ' . (string) $vehicule['lieu_ville'] : ''),
    "Date d'entrée"         => (string) $vehicule['date_entree'],
    'Sortie prévisionnelle' => (string) $vehicule['date_sortie_prevue'],
    'Sortie effective'      => ($vehicule['date_sortie_effective'] ?? null) !== null ? (string) $vehicule['date_sortie_effective'] : '—',
    'Sortie réelle'        => ($vehicule['sortie_reelle'] ?? null) !== null ? (string) $vehicule['sortie_reelle'] : '—',
    'Sortie Angelus'       => ($vehicule['sortie_angelus'] ?? null) !== null ? (string) $vehicule['sortie_angelus'] : '—',
    'Durée contrat (mois)'  => ($vehicule['duree_contrat'] ?? null) !== null ? (string) $vehicule['duree_contrat'] : '—',
    'Kilométrage maximum'   => ($vehicule['km_maxi'] ?? null) !== null ? number_format((int) $vehicule['km_maxi'], 0, ',', ' ') . ' km' : '—',
    'Équipé d\'un hayon'          => !empty($vehicule['hayon']) ? 'Oui' : 'Non',
    'Temps contrôle hayon'        => !empty($vehicule['hayon']) && !empty($vehicule['temps_controle_hayon']) ? (string) $vehicule['temps_controle_hayon'] . ' mois' : '—',
    'Dernier contrôle hayon'      => !empty($vehicule['hayon']) && !empty($vehicule['date_dernier_controle_hayon']) ? (string) $vehicule['date_dernier_controle_hayon'] : '—',
    'Prochain contrôle hayon'     => !empty($vehicule['hayon']) && !empty($vehicule['date_prochain_controle_hayon'])
        ? (string) $vehicule['date_prochain_controle_hayon']
        : ((!empty($vehicule['hayon']) && !empty($vehicule['date_dernier_controle_hayon']) && !empty($vehicule['temps_controle_hayon']))
            ? date('Y-m-d', (int) strtotime('+' . (int) $vehicule['temps_controle_hayon'] . ' months', strtotime((string) $vehicule['date_dernier_controle_hayon'])))
            : '—'),
    'ImmatriculationUsage'        => (int) $vehicule['immatricule'] === 1 ? 'Non immatriculé' : 'Immatriculé',
    'Dernier kilométrage'         => $dernierKm > 0 ? number_format($dernierKm, 0, ',', ' ') . ' km' : '—',
];
?>

<div class="row row-cards">
    <!-- Identité et cycle d'exploitation -->
    <div class="col-lg-4 d-flex">
        <div class="card w-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Fiche véhicule</h3>
                <div class="card-actions">
                    <span class="badge <?= $classes[(string) $vehicule['statut']] ?? 'bg-secondary-lt' ?> badge-statut">
                        <?= $e(Vehicle::STATUTS[(string) $vehicule['statut']] ?? (string) $vehicule['statut']) ?>
                    </span>
                </div>
            </div>
            <div class="card-body flex-fill">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <?php if (!empty($vehicule['marque_logo'])): ?>
                        <img src="<?= $e(\Core\Url::upload('marques/' . $vehicule['marque_logo'])) ?>"
                             alt="<?= $e($vehicule['marque_nom']) ?>"
                             class="rounded bg-white p-2 border shadow-xs"
                             style="max-height: 70px; max-width: 120px; height: 60px; width: auto; object-fit: contain;">
                    <?php endif; ?>
                    <div>
                        <h2 class="mb-0"><?= $e($vehicule['marque_nom'] . ' ' . $vehicule['modele_nom']) ?></h2>
                        <div class="text-secondary"><?= $e($vehicule['immatriculation']) ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Temps de détention restant</span>
                        <strong>
                            <?php if ($jours === null): ?>Restitué
                            <?php elseif ($jours < 0): ?>Retard de <?= abs($jours) ?> jours
                            <?php else: ?>J-<?= (int) $jours ?>
                            <?php endif; ?>
                        </strong>
                    </div>
                    <?php
                    $duree = max(1, (int) ((strtotime((string) $vehicule['date_sortie_prevue']) - strtotime((string) $vehicule['date_entree'])) / 86400));
                    $pct = $jours === null ? 100 : max(0, min(100, (int) round((1 - max(0, $jours) / $duree) * 100)));
                    ?>
                    <div class="jauge">
                        <span style="width:<?= $pct ?>%;background:<?= $pct > 85 ? '#d63939' : '#206bc4' ?>"></span>
                    </div>
                </div>

                <?php if (!empty($vehicule['modele_photo'])): ?>
                    <div class="mb-3 text-center">
                        <img src="<?= $e(\Core\Url::upload('modeles/' . $vehicule['modele_photo'])) ?>"
                             alt="<?= $e($vehicule['modele_nom']) ?>"
                             class="rounded border shadow-xs img-fluid"
                             style="max-height: 200px; width: 100%; object-fit: cover;">
                    </div>
                <?php endif; ?>

                <div class="datagrid">
                    <?php foreach ($liens as $cle => $valeur): ?>
                        <div class="datagrid-item">
                            <div class="datagrid-title"><?= $e((string) $cle) ?></div>
                            <div class="datagrid-content"><?= $e($valeur) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Cumul entretien HT</div>
                        <div class="datagrid-content"><?= $e(number_format($coutTotal, 0, ',', ' ') . ' €') ?></div>
                    </div>
                </div>

                <?php if ((string) $vehicule['commentaire'] !== ''): ?>
                    <div class="alert alert-info mt-3 mb-0"><?= nl2br($e((string) $vehicule['commentaire'])) ?></div>
                <?php endif; ?>
            </div>
            <div class="card-footer d-flex no-print">
                <a href="<?= $e($base_url . '/vehicules') ?>" class="btn btn-outline-secondary me-2">Retour à la flotte</a>
                <?php if ($modifiable): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-vehicule">Modifier</button>
                    <form method="post" action="<?= $e($base_url . '/vehicules/supprimer') ?>" class="ms-auto"
                          data-confirmer="Supprimer définitivement le véhicule <?= $e($vehicule['immatriculation']) ?> ? Son historique d'entretien et ses incidents seront également perdus.">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int) $vehicule['id'] ?>">
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Entretien et incidents -->
    <div class="col-lg-8 d-flex flex-column gap-3">
        <div class="card carte-partagee d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Historique des révisions</h3>
                <?php if ($modifiable): ?>
                    <div class="card-actions">
                        <a href="<?= $e($base_url . '/entretien?vehicule_id=' . (int) $vehicule['id']) ?>" class="btn btn-primary btn-sm">
                            Ajouter une prestation
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="table-responsive flex-fill">
                <?php if ($entretiens === []): ?>
                    <div class="empty-state">Aucune révision enregistrée pour ce véhicule.</div>
                <?php else: ?>
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Date</th><th>Prestation</th><th>Catégorie</th><th>Kilométrage</th><th class="w-1">Coût HT</th></tr></thead>
                        <tbody>
                        <?php foreach ($entretiens as $m): ?>
                            <tr>
                                <td><?= $e((string) $m['date_operation']) ?></td>
                                <td><?= $e((string) $m['type_libelle']) ?></td>
                                <td><span class="badge bg-blue-lt"><?= $e(ucfirst((string) $m['type_categorie'])) ?></span></td>
                                <td class="text-secondary"><?= $e(number_format((int) $m['kilometrage'], 0, ',', ' ')) ?> km</td>
                                <td class="text-end fw-bold"><?= $e(number_format((float) $m['cout_ht'], 2, ',', ' ')) ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="4">Cumul entretien HT</th>
                            <th class="text-end"><?= $e(number_format($coutTotal, 2, ',', ' ')) ?> €</th>
                        </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card carte-partagee d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Incidents &amp; sinistres</h3>
                <?php if ($modifiable): ?>
                    <div class="card-actions">
                        <a href="<?= $e($base_url . '/incidents?vehicule_id=' . (int) $vehicule['id'] . '&nouveau=1') ?>"
                           class="btn btn-primary btn-sm">Signaler un incident</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="table-responsive flex-fill">
                <?php
                $incidents = \Models\Incident::search(['vehicule_id' => (int) $vehicule['id']]);
                ?>
                <?php if ($incidents === []): ?>
                    <div class="empty-state">Aucun incident enregistré pour ce véhicule.</div>
                <?php else: ?>
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Date</th><th>Type</th><th>Lieu</th><th>Statut</th><th class="w-1">Resp.</th><th class="w-1"></th></tr></thead>
                        <tbody>
                        <?php foreach ($incidents as $i): ?>
                            <tr>
                                <td><?= $e((string) $i['date_incident']) ?></td>
                                <td><?= $e(Incident::TYPES[(string) $i['type']] ?? (string) $i['type']) ?></td>
                                <td class="text-secondary"><?= $e((string) ($i['lieu'] ?: '—')) ?></td>
                                <td>
                                    <span class="badge <?= $i['statut'] === 'cloture' ? 'bg-green-lt' : ($i['statut'] === 'en_traitement' ? 'bg-orange-lt' : 'bg-red-lt') ?> badge-statut">
                                        <?= $e(Incident::STATUTS[(string) $i['statut']] ?? (string) $i['statut']) ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= (int) $i['responsable'] === 1 ? 'Oui' : 'Non' ?></td>
                                <td class="text-end">
                                    <a href="<?= $e($base_url . '/incidents/voir?id=' . (int) $i['id']) ?>" class="btn btn-sm">Dossier</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Graphique d'évolution kilométrique mensuelle -->
        <div class="card carte-partagee d-flex flex-column">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><?= $icone('chart-line', 'me-2') ?>Évolution kilométrique (12 derniers mois)</h3>
                <a href="<?= $e($base_url . '/kilometrage') ?>" class="btn btn-outline-secondary btn-sm">Saisir un relevé</a>
            </div>
            <div class="card-body">
                <div style="height: 240px; position: relative;">
                    <canvas id="chart-kilometrage-vehicule"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('chart-kilometrage-vehicule');
    if (!el) return;

    const donnees = <?= json_encode($historiqueKm ?? ['labels' => [], 'distances' => []], JSON_UNESCAPED_UNICODE) ?>;

    new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
            labels: donnees.labels,
            datasets: [{
                label: 'Distance parcourue (km)',
                data: donnees.distances,
                backgroundColor: 'rgba(32, 107, 196, 0.25)',
                borderColor: '#206bc4',
                borderWidth: 2,
                borderRadius: 4,
                hoverBackgroundColor: '#206bc4'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` +${ctx.raw.toLocaleString('fr-FR')} km parcourus`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (val) => val + ' km' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});
</script>

<?php if ($modifiable): ?>
    <?php $this->partial('vehicles/_formulaire', [
        'base_url'   => $base_url,
        'vehicule'   => $vehicule,
        'nomenclature' => $nomenclature,
        'statuts'    => $statuts,
    ]); ?>
<?php endif; ?>
