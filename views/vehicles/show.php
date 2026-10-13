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
use Models\VehicleDocument;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);
$modifiable = Auth::can(Auth::ROLE_MODIFICATION);
$peutAjouterDocument = Auth::can(Auth::ROLE_DOCUMENTALISTE);
$documents = $documents ?? [];
$poids = static function (int $octets): string {
    if ($octets >= 1048576) {
        return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
    }
    return number_format(max(1, (int) round($octets / 1024)), 0, ',', ' ') . ' Ko';
};
$jours = Vehicle::joursRestants($vehicule);
$coutTotal = array_sum(array_map(static fn (array $m): float => (float) $m['cout_ht'], $entretiens));
$dernierKm = (int) ($vehicule['kilometrage'] ?? 0);
foreach ($entretiens as $m) {
    $dernierKm = max($dernierKm, (int) $m['kilometrage']);
}
try {
    $maxOdo = (int) \Core\Database::scalar(
        'SELECT MAX(index_km) FROM releves_odometre WHERE vehicule_id = :id',
        ['id' => (int) $vehicule['id']],
        0
    );
    $dernierKm = max($dernierKm, $maxOdo);
} catch (\Throwable) {}
$kmMaxi = !empty($vehicule['km_maxi']) ? (int) $vehicule['km_maxi'] : null;
$pctKm = ($kmMaxi !== null && $kmMaxi > 0) ? (int) round(($dernierKm / $kmMaxi) * 100) : 0;

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

<?php if (!empty($vehicule['deleted_at'])): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between mb-3 shadow-xs">
        <div class="d-flex align-items-center gap-2">
            <span class="avatar bg-warning text-white rounded">
                <?= $icone('triangle-exclamation') ?>
            </span>
            <div>
                <strong class="d-block text-warning-emphasis">Véhicule archivé (supprimé logiquement)</strong>
                <span class="text-secondary small">
                    Ce véhicule est masqué pour tous les profils non-administrateurs depuis le <?= date('d/m/Y à H:i', strtotime((string) $vehicule['deleted_at'])) ?>.
                </span>
            </div>
        </div>
        <?php if (Auth::can(Auth::ROLE_ADMIN)): ?>
            <div class="d-flex gap-2">
                <form method="post" action="<?= $e($base_url . '/vehicules/restaurer') ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $vehicule['id'] ?>">
                    <button type="submit" class="btn btn-success btn-sm">
                        <?= $icone('clock-rotate-left', 'me-1') ?>Restaurer le véhicule
                    </button>
                </form>
                <form method="post" action="<?= $e($base_url . '/vehicules/supprimer-definitif') ?>"
                      data-confirmer="Attention : Supprimer DÉFINITIVEMENT le véhicule <?= $e($vehicule['immatriculation']) ?> ? Cette action est irréversible.">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $vehicule['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <?= $icone('trash-can', 'me-1') ?>Supprimer définitivement
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row row-cards">
    <!-- Identité et cycle d'exploitation -->
    <div class="col-lg-4 d-flex">
        <div class="card w-100 d-flex flex-column">
            <div class="card-header">
                <h3 class="card-title">Fiche véhicule</h3>
                <div class="card-actions">
                    <?php if (!empty($vehicule['deleted_at'])): ?>
                        <span class="badge bg-warning-lt text-warning me-1">
                            <?= $icone('box-archive', 'me-1') ?>Archivé
                        </span>
                    <?php endif; ?>
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

                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Kilométrage (réalisé / max)</span>
                        <strong>
                            <?php if ($kmMaxi !== null && $kmMaxi > 0): ?>
                                <?php if ($dernierKm > $kmMaxi): ?>
                                    <span class="text-danger" title="Dépassement de <?= number_format($dernierKm - $kmMaxi, 0, ',', ' ') ?> km">
                                        <?= number_format($dernierKm, 0, ',', ' ') ?> / <?= number_format($kmMaxi, 0, ',', ' ') ?> km
                                        (+<?= number_format($dernierKm - $kmMaxi, 0, ',', ' ') ?>)
                                    </span>
                                <?php else: ?>
                                    <?= number_format($dernierKm, 0, ',', ' ') ?> / <?= number_format($kmMaxi, 0, ',', ' ') ?> km
                                    <span class="text-secondary">(<?= $pctKm ?>%)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= number_format($dernierKm, 0, ',', ' ') ?> km <span class="text-secondary fw-normal">(aucun plafond)</span>
                            <?php endif; ?>
                        </strong>
                    </div>
                    <?php
                    $largeurKm = ($kmMaxi !== null && $kmMaxi > 0) ? min(100, max(0, $pctKm)) : 0;
                    $couleurKm = $pctKm > 100 ? '#d63939' : ($pctKm > 85 ? '#f59f00' : '#206bc4');
                    ?>
                    <div class="jauge">
                        <span style="width:<?= $largeurKm ?>%;background:<?= $couleurKm ?>"></span>
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
                    <?php if (!empty($vehicule['deleted_at'])): ?>
                        <?php if (Auth::can(Auth::ROLE_ADMIN)): ?>
                            <form method="post" action="<?= $e($base_url . '/vehicules/restaurer') ?>" class="ms-auto me-2">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $vehicule['id'] ?>">
                                <button type="submit" class="btn btn-success">
                                    <?= $icone('clock-rotate-left', 'me-1') ?>Restaurer
                                </button>
                            </form>
                            <form method="post" action="<?= $e($base_url . '/vehicules/supprimer-definitif') ?>"
                                  data-confirmer="Attention : Supprimer DÉFINITIVEMENT le véhicule <?= $e($vehicule['immatriculation']) ?> ? Cette action est irréversible.">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $vehicule['id'] ?>">
                                <button type="submit" class="btn btn-danger">Supprimer définitivement</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="post" action="<?= $e($base_url . '/vehicules/supprimer') ?>" class="ms-auto"
                              data-confirmer="Archiver le véhicule <?= $e($vehicule['immatriculation']) ?> ? Il sera masqué pour les profils non-administrateurs.">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int) $vehicule['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger">
                                <?= $icone('box-archive', 'me-1') ?>Archiver
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Entretien, incidents et documents -->
    <div class="col-lg-8 d-flex flex-column gap-3">
        <!-- Documents & Justificatifs (Carte grise, assurance, etc.) -->
        <div class="card carte-partagee d-flex flex-column">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">
                    <?= $icone('folder-open', 'me-2 text-primary') ?>Documents &amp; Justificatifs
                </h3>
                <div class="card-actions d-flex align-items-center gap-2">
                    <span class="badge bg-secondary-lt"><?= count($documents) ?> document<?= count($documents) > 1 ? 's' : '' ?></span>
                    <?php if ($peutAjouterDocument): ?>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-ajouter-document">
                            <?= $icone('plus', 'me-1') ?>Ajouter un document
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive flex-fill">
                <?php if ($documents === []): ?>
                    <div class="empty-state py-4 text-center">
                        <div class="text-secondary mb-2">
                            <i class="fa-solid fa-folder-open fa-2x"></i>
                        </div>
                        <p class="fw-bold mb-1">Aucun document enregistré pour ce véhicule</p>
                        <p class="text-secondary small mb-3">
                            Enregistrez ici la carte grise, l'attestation d'assurance, le dernier contrôle technique ou les contrats.
                        </p>
                        <?php if ($peutAjouterDocument): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modal-ajouter-document">
                                <?= $icone('plus', 'me-1') ?>Ajouter un premier document
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="table table-vcenter card-table table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Document</th>
                                <th>Échéance / Validité</th>
                                <th>Ajouté le</th>
                                <th class="w-1 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $d): ?>
                                <?php
                                $typeKey   = (string) ($d['type'] ?? 'autre');
                                $badgeCls  = VehicleDocument::TYPES_BADGES[$typeKey] ?? 'bg-secondary-lt text-secondary';
                                $iconName  = VehicleDocument::TYPES_ICONES[$typeKey] ?? 'file-lines';
                                $typeLabel = VehicleDocument::TYPES[$typeKey] ?? ucfirst($typeKey);
                                
                                $isPdf = (string) $d['mime'] === 'application/pdf';
                                $isImg = VehicleDocument::estImage($d);
                                $present = VehicleDocument::fichierPresent($d);
                                $apercuUrl = $base_url . '/vehicules/document/apercu?id=' . (int) $d['id'];
                                $telechargerUrl = $base_url . '/vehicules/document/telecharger?id=' . (int) $d['id'];
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= $badgeCls ?> text-nowrap">
                                            <?= $icone($iconName, 'me-1') ?><?= $e($typeLabel) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($isImg && $present): ?>
                                                <a class="vignette-fichier me-2 document"
                                                   href="<?= $e($apercuUrl) ?>"
                                                   data-document="<?= $e($apercuUrl) ?>"
                                                   data-nom="<?= $e((string) $d['titre']) ?>"
                                                   data-type="image"
                                                   data-mime="<?= $e(strtoupper((string) $d['mime'])) ?>"
                                                   data-taille="<?= $e($poids((int) $d['taille'])) ?>"
                                                   title="Aperçu">
                                                    <img src="<?= $e($apercuUrl) ?>" alt="<?= $e((string) $d['titre']) ?>"
                                                         width="36" height="36" class="rounded border" style="object-fit: cover;">
                                                </a>
                                            <?php else: ?>
                                                <span class="vignette-fichier vignette-fichier-icone me-2" aria-hidden="true" style="width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;background:#f1f5f9;border-radius:4px;">
                                                    <i class="fa-solid <?= $isPdf ? 'fa-file-pdf text-danger' : 'fa-file-lines text-secondary' ?>"></i>
                                                </span>
                                            <?php endif; ?>
                                            <div>
                                                <?php if ($present && ($isPdf || $isImg)): ?>
                                                    <a href="<?= $e($apercuUrl) ?>"
                                                       class="text-reset fw-bold text-truncate d-block document"
                                                       data-document="<?= $e($apercuUrl) ?>"
                                                       data-nom="<?= $e((string) $d['titre']) ?>"
                                                       data-type="<?= $isPdf ? 'pdf' : 'image' ?>"
                                                       data-mime="<?= $e(strtoupper((string) $d['mime'])) ?>"
                                                       data-taille="<?= $e($poids((int) $d['taille'])) ?>"
                                                       title="Ouvrir dans la visionneuse">
                                                        <?= $e((string) $d['titre']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <div class="fw-bold text-truncate"><?= $e((string) $d['titre']) ?></div>
                                                <?php endif; ?>
                                                <div class="small text-secondary text-truncate">
                                                    <?= $e((string) $d['nom_original']) ?> &middot; <?= $e($poids((int) $d['taille'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($d['date_expiration'])): ?>
                                            <?php
                                            $joursExp = (int) ceil((strtotime((string) $d['date_expiration']) - strtotime('today')) / 86400);
                                            ?>
                                            <?php if ($joursExp < 0): ?>
                                                <span class="badge bg-danger-lt text-danger text-nowrap" title="Échéance dépassée">
                                                    <?= $icone('triangle-exclamation', 'me-1') ?>Expiré (<?= date('d/m/Y', strtotime((string) $d['date_expiration'])) ?>)
                                                </span>
                                            <?php elseif ($joursExp <= 30): ?>
                                                <span class="badge bg-warning-lt text-warning text-nowrap" title="Échéance dans <?= $joursExp ?> jours">
                                                    <?= $icone('clock', 'me-1') ?>Expire dans <?= $joursExp ?> j (<?= date('d/m/Y', strtotime((string) $d['date_expiration'])) ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-lt text-success text-nowrap">
                                                    <?= $icone('circle-check', 'me-1') ?>Jusqu'au <?= date('d/m/Y', strtotime((string) $d['date_expiration'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-secondary">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-secondary small text-nowrap">
                                        <?= date('d/m/Y', strtotime((string) $d['created_at'])) ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <?php if ($present && ($isPdf || $isImg)): ?>
                                                <a class="btn btn-sm btn-icon btn-outline-secondary document"
                                                   href="<?= $e($apercuUrl) ?>"
                                                   data-document="<?= $e($apercuUrl) ?>"
                                                   data-nom="<?= $e((string) $d['titre']) ?>"
                                                   data-type="<?= $isPdf ? 'pdf' : 'image' ?>"
                                                   data-mime="<?= $e(strtoupper((string) $d['mime'])) ?>"
                                                   data-taille="<?= $e($poids((int) $d['taille'])) ?>"
                                                   title="Consulter l'aperçu">
                                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a class="btn btn-sm btn-icon btn-outline-secondary"
                                               href="<?= $e($telechargerUrl) ?>"
                                               title="Télécharger">
                                                <i class="fa-solid fa-download" aria-hidden="true"></i>
                                            </a>
                                            <?php if ($modifiable): ?>
                                                <?php if (!empty($d['deleted_at'])): ?>
                                                    <?php if (Auth::can(Auth::ROLE_ADMIN)): ?>
                                                        <form method="post" action="<?= $e($base_url . '/vehicules/document/restaurer') ?>" class="d-inline">
                                                            <?= Csrf::field() ?>
                                                            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-success" title="Restaurer le document">
                                                                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                                                            </button>
                                                        </form>
                                                        <form method="post" action="<?= $e($base_url . '/vehicules/document/supprimer-definitif') ?>"
                                                              class="d-inline"
                                                              data-confirmer="Supprimer DÉFINITIVEMENT ce document (<?= $e((string) $d['titre']) ?>) ?">
                                                            <?= Csrf::field() ?>
                                                            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-icon btn-danger" title="Supprimer définitivement">
                                                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <form method="post" action="<?= $e($base_url . '/vehicules/document/supprimer') ?>"
                                                          class="d-inline"
                                                          data-confirmer="Archiver ce document (<?= $e((string) $d['titre']) ?>) ? Il sera masqué pour les profils non-administrateurs.">
                                                        <?= Csrf::field() ?>
                                                        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" title="Archiver le document">
                                                            <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

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
                                <td><?= !empty($i['date_incident']) ? date('d-m-Y', strtotime((string) $i['date_incident'])) : '—' ?></td>
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

    // Visionneuse de documents
    function initialiserVisionneuseVehicule() {
        var modale = document.getElementById('modal-document');
        if (!modale) { return; }

        var image = document.getElementById('visionneuse-image');
        var cadre = document.getElementById('visionneuse-pdf');
        var titre = document.getElementById('titre-document');
        var meta = document.getElementById('document-meta');
        var lien = document.getElementById('document-telecharger');

        modale.addEventListener('show.bs.modal', function (evenement) {
            var declencheur = evenement.relatedTarget;
            if (!declencheur || !declencheur.hasAttribute('data-document')) { return; }

            var url = declencheur.getAttribute('data-document');
            var type = declencheur.getAttribute('data-type');

            titre.textContent = declencheur.getAttribute('data-nom') || 'Document';

            var parties = [];
            if (declencheur.getAttribute('data-mime')) {
                parties.push(declencheur.getAttribute('data-mime'));
            }
            if (declencheur.getAttribute('data-taille')) {
                parties.push(declencheur.getAttribute('data-taille'));
            }
            meta.textContent = parties.join(' · ');

            lien.setAttribute('href', url.replace('/apercu?', '/telecharger?'));

            if (type === 'pdf') {
                image.hidden = true;
                image.removeAttribute('src');
                cadre.hidden = false;
                cadre.setAttribute('src', url);
                return;
            }

            cadre.hidden = true;
            cadre.setAttribute('src', 'about:blank');
            image.hidden = false;
            image.setAttribute('src', url);
            image.setAttribute('alt', titre.textContent);
        });

        modale.addEventListener('hidden.bs.modal', function () {
            image.hidden = true;
            image.removeAttribute('src');
            cadre.hidden = true;
            cadre.setAttribute('src', 'about:blank');
        });

        document.querySelectorAll('[data-document]').forEach(function (element) {
            element.addEventListener('click', function (evenement) {
                if (evenement.ctrlKey || evenement.metaKey || evenement.shiftKey || evenement.button !== 0) {
                    return;
                }
                evenement.preventDefault();
                bootstrap.Modal.getOrCreateInstance(modale).show(evenement.currentTarget);
            });
        });
    }

    initialiserVisionneuseVehicule();
});
</script>

<!-- Modale d'ajout d'un document -->
<?php if ($peutAjouterDocument): ?>
<div class="modal modal-blur fade" id="modal-ajouter-document" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="post" action="<?= $e($base_url . '/vehicules/document') ?>" enctype="multipart/form-data">
                <?= Csrf::field() ?>
                <input type="hidden" name="vehicule_id" value="<?= (int) $vehicule['id'] ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><?= $icone('folder-open', 'me-2 text-primary') ?>Ajouter un document au véhicule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required" for="doc-type">Type de document</label>
                        <select class="form-select" id="doc-type" name="type" required>
                            <?php foreach (VehicleDocument::TYPES as $code => $libelle): ?>
                                <option value="<?= $e($code) ?>" <?= $code === 'carte_grise' ? 'selected' : '' ?>>
                                    <?= $e($libelle) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="doc-titre">Titre / Désignation</label>
                        <input type="text" class="form-control" id="doc-titre" name="titre"
                               placeholder="Ex : Carte grise définitive, Attestation AXA 2026-2027..." maxlength="140">
                        <small class="form-hint">Facultatif : si laissé vide, le type du document sera utilisé.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="doc-expiration">Date d'échéance / validité</label>
                        <input type="date" class="form-control" id="doc-expiration" name="date_expiration">
                        <small class="form-hint">Facultatif : utile pour suivre l'assurance ou le contrôle technique.</small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label required" for="doc-fichier">Fichier</label>
                        <input type="file" class="form-control" id="doc-fichier" name="fichier" required
                               accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <small class="form-hint">PDF, JPG, PNG ou WEBP — 8 Mo maximum.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <?= $icone('check', 'me-1') ?>Enregistrer le document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modale de visionneuse de document -->
<div class="modal modal-blur fade" id="modal-document" tabindex="-1" role="dialog" aria-hidden="true"
     aria-labelledby="titre-document">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="titre-document">Document</h5>
                <div class="modal-actions">
                    <a class="btn btn-sm btn-outline-primary" id="document-telecharger" href="#" download>
                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                        Télécharger
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="visionneuse" id="visionneuse-support">
                    <img class="visionneuse-image" id="visionneuse-image" alt="" src="" hidden>
                    <iframe class="visionneuse-pdf" id="visionneuse-pdf" title="Visionneuse PDF"
                            src="about:blank" hidden></iframe>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="small text-secondary" id="document-meta"></div>
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php if ($modifiable): ?>
    <?php $this->partial('vehicles/_formulaire', [
        'base_url'   => $base_url,
        'vehicule'   => $vehicule,
        'nomenclature' => $nomenclature,
        'statuts'    => $statuts,
    ]); ?>
<?php endif; ?>
