<?php
declare(strict_types=1);

/**
 * Administration : Journal d'audit et de traçabilité des modifications.
 *
 * Permet de rechercher et filtrer les insertions, modifications et suppressions
 * effectuées sur le parc de véhicules, révisions, incidents, kilométrages,
 * documents et utilisateurs.
 *
 * @var array $logs           Liste des logs pour la page courante
 * @var array $filtres        Filtres actifs
 * @var array $utilisateurs   Comptes utilisateurs pour le filtre
 * @var array $actions        Libellés des types d'actions
 * @var array $entites        Libellés des entités / modules
 * @var int   $total          Nombre total d'enregistrements correspondants
 * @var int   $page           Page actuelle
 * @var int   $nbPages        Nombre total de pages
 * @var int   $parPage        Nombre de résultats par page
 * @var int   $offset         Décalage courant
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;
use Models\AuditLog;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

$urlParams = array_filter($filtres, static fn ($v) => $v !== '' && $v !== null && $v !== 0);
$urlFiltres = static function (array $extra = []) use ($base_url, $urlParams, $parPage): string {
    $p = array_merge($urlParams, $extra);
    if ($parPage !== 25) {
        $p['par_page'] = $parPage;
    }
    return htmlspecialchars($base_url . '/admin/journal' . ($p !== [] ? '?' . http_build_query($p) : ''), ENT_QUOTES, 'UTF-8');
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
?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <?= $icone('clock-rotate-left', 'me-2 text-primary') ?>Journal d'audit &amp; Traçabilité
        </h3>
        <div class="card-actions d-flex gap-2">
            <a href="<?= $e($base_url . '/admin/journal/export-csv?' . http_build_query($urlParams)) ?>"
               class="btn btn-outline-secondary btn-sm" title="Exporter au format CSV">
                <?= $icone('file-arrow-down', 'me-1') ?>Exporter CSV
            </a>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modal-purger-journal">
                <?= $icone('trash-can', 'me-1') ?>Purger manuellement...
            </button>
        </div>
    </div>

    <!-- Formulaire de filtrage et recherche -->
    <div class="card-body border-bottom py-3">
        <form method="get" action="<?= $e($base_url . '/admin/journal') ?>" class="row g-2 align-items-end">
            <!-- Immatriculation -->
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label" for="immatriculation">Immatriculation</label>
                <div class="input-icon">
                    <span class="input-icon-addon"><?= $icone('car') ?></span>
                    <input type="text" class="form-control text-uppercase" id="immatriculation" name="immatriculation"
                           value="<?= $e((string) $filtres['immatriculation']) ?>" placeholder="Ex : AA-123-AA" autocomplete="off"
                           data-suggestions="<?= $e(json_encode(
                               array_map(static fn (array $i): array => ['valeur' => $i['immatriculation'], 'libelle' => $i['libelle']], $immatriculations ?? []),
                               JSON_UNESCAPED_UNICODE
                           ) ?: '[]') ?>"
                           data-suggestions-submit>
                </div>
            </div>

            <!-- Plage de dates : Début -->
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label" for="date_debut">Du (date début)</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut"
                       value="<?= $e((string) $filtres['date_debut']) ?>">
            </div>

            <!-- Plage de dates : Fin -->
            <div class="col-6 col-sm-3 col-md-2">
                <label class="form-label" for="date_fin">Au (date fin)</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin"
                       value="<?= $e((string) $filtres['date_fin']) ?>">
            </div>

            <!-- Auteur / Utilisateur -->
            <div class="col-12 col-sm-4 col-md-2">
                <label class="form-label" for="utilisateur_id">Utilisateur</label>
                <select class="form-select" id="utilisateur_id" name="utilisateur_id">
                    <option value="">Tous les auteurs</option>
                    <?php foreach ($utilisateurs as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (int) $filtres['utilisateur_id'] === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= $e((string) $u['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Action -->
            <div class="col-6 col-sm-4 col-md-2">
                <label class="form-label" for="action">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">Toutes</option>
                    <?php foreach ($actions as $code => $libelle): ?>
                        <option value="<?= $e($code) ?>" <?= $filtres['action'] === $code ? 'selected' : '' ?>>
                            <?= $e($libelle) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Entité / Module -->
            <div class="col-6 col-sm-4 col-md-2">
                <label class="form-label" for="entite">Module</label>
                <select class="form-select" id="entite" name="entite">
                    <option value="">Tous</option>
                    <?php foreach ($entites as $code => $libelle): ?>
                        <option value="<?= $e($code) ?>" <?= $filtres['entite'] === $code ? 'selected' : '' ?>>
                            <?= $e($libelle) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Mots-clés libres -->
            <div class="col-12 col-md-10">
                <div class="input-icon">
                    <span class="input-icon-addon"><?= $icone('magnifying-glass') ?></span>
                    <input type="text" class="form-control" id="q" name="q"
                           value="<?= $e((string) $filtres['q']) ?>" placeholder="Rechercher dans la description ou les détails..." autocomplete="off">
                </div>
            </div>

            <!-- Boutons Soumettre / Réinitialiser -->
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill" title="Filtrer">
                    <?= $icone('magnifying-glass', 'me-1') ?>Filtrer
                </button>
                <a href="<?= $e($base_url . '/admin/journal') ?>" class="btn btn-outline-secondary" title="Réinitialiser les filtres">✕</a>
            </div>

            <!-- Barre récapitulative -->
            <div class="col-12 mt-2 d-flex flex-wrap align-items-center justify-content-between gap-2 text-secondary small">
                <div>
                    <?php if ($total > 0): ?>
                        Affichage de <strong><?= $offset + 1 ?></strong> à <strong><?= min($total, $offset + count($logs)) ?></strong>
                        sur <strong><?= number_format($total, 0, ',', ' ') ?></strong> entrée(s) trouvée(s)
                    <?php else: ?>
                        0 entrée trouvée
                    <?php endif; ?>
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

    <!-- Tableau des logs -->
    <div class="table-responsive">
        <?php if ($logs === []): ?>
            <div class="empty-state py-5 text-center">
                <div class="text-secondary mb-2">
                    <i class="fa-solid fa-clock-rotate-left fa-3x"></i>
                </div>
                <p class="fw-bold mb-1">Aucune trace dans le journal d'audit</p>
                <p class="text-secondary small mb-3">
                    Aucun événement ne correspond aux critères de recherche sélectionnés.
                </p>
                <a href="<?= $e($base_url . '/admin/journal') ?>" class="btn btn-outline-primary btn-sm">
                    Réinitialiser les filtres
                </a>
            </div>
        <?php else: ?>
            <table class="table table-vcenter card-table table-hover table-striped">
                <thead>
                    <tr>
                        <th class="w-1">Date &amp; Heure</th>
                        <th>Auteur</th>
                        <th class="text-center">Action</th>
                        <th>Module</th>
                        <th>Véhicule</th>
                        <th>Description</th>
                        <th class="w-1 text-end">Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <?php
                        $actionKey  = (string) $l['action'];
                        $actionBadge = AuditLog::ACTIONS_BADGES[$actionKey] ?? 'bg-secondary-lt';
                        $actionIcon  = AuditLog::ACTIONS_ICONES[$actionKey] ?? 'circle';
                        $actionLabel = AuditLog::ACTIONS[$actionKey] ?? ucfirst($actionKey);

                        $entiteKey   = (string) $l['entite'];
                        $entiteBadge = AuditLog::ENTITES_BADGES[$entiteKey] ?? 'bg-secondary-lt';
                        $entiteLabel = AuditLog::ENTITES[$entiteKey] ?? ucfirst($entiteKey);

                        $auteurNom   = (string) ($l['utilisateur_nom'] ?: $l['auteur_actuel_nom'] ?: 'Système');
                        $immat       = trim((string) ($l['immatriculation'] ?? ''));
                        $dateHeure   = date('d/m/Y H:i:s', strtotime((string) $l['created_at']));
                        $aDetails    = !empty($l['details']) && $l['details'] !== '[]' && $l['details'] !== 'null';
                        ?>
                        <tr>
                            <!-- Date & Heure -->
                            <td class="text-nowrap text-secondary small">
                                <span title="<?= $e((string) $l['created_at']) ?>">
                                    <?= $icone('calendar', 'me-1 text-muted') ?><?= $e($dateHeure) ?>
                                </span>
                            </td>

                            <!-- Auteur -->
                            <td class="text-nowrap">
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-xs rounded me-2 bg-secondary-lt">
                                        <?= $icone('user', 'small') ?>
                                    </span>
                                    <div>
                                        <div class="fw-bold"><?= $e($auteurNom) ?></div>
                                        <?php if (!empty($l['ip'])): ?>
                                            <div class="text-muted small"><?= $e((string) $l['ip']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Action -->
                            <td class="text-center text-nowrap">
                                <span class="badge <?= $actionBadge ?>">
                                    <?= $icone($actionIcon, 'me-1') ?><?= $e($actionLabel) ?>
                                </span>
                            </td>

                            <!-- Module -->
                            <td class="text-nowrap">
                                <span class="badge <?= $entiteBadge ?>">
                                    <?= $e($entiteLabel) ?>
                                </span>
                            </td>

                            <!-- Véhicule / Immatriculation -->
                            <td class="text-nowrap">
                                <?php if ($immat !== ''): ?>
                                    <a href="<?= $e($base_url . '/vehicules?q=' . urlencode($immat)) ?>"
                                       class="badge bg-dark-lt text-dark font-monospace text-decoration-none"
                                       title="Filtrer le parc sur cette immatriculation">
                                        <?= $icone('car', 'me-1') ?><?= $e($immat) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Description -->
                            <td>
                                <div class="text-truncate" style="max-width: 480px;" title="<?= $e((string) $l['description']) ?>">
                                    <?= $e((string) $l['description']) ?>
                                </div>
                            </td>

                            <!-- Détails (Modale) -->
                            <td class="text-end text-nowrap">
                                <?php if ($aDetails): ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary"
                                            title="Consulter les données de l'opération"
                                            data-bs-toggle="modal" data-bs-target="#modal-details-log"
                                            data-log-id="<?= (int) $l['id'] ?>"
                                            data-log-date="<?= $e($dateHeure) ?>"
                                            data-log-auteur="<?= $e($auteurNom) ?>"
                                            data-log-action="<?= $e($actionLabel) ?>"
                                            data-log-module="<?= $e($entiteLabel) ?>"
                                            data-log-immat="<?= $e($immat ?: '—') ?>"
                                            data-log-ip="<?= $e((string) ($l['ip'] ?? '—')) ?>"
                                            data-log-desc="<?= $e((string) $l['description']) ?>"
                                            data-log-details="<?= $e((string) $l['details']) ?>">
                                        <?= $icone('eye') ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($nbPages > 1): ?>
        <div class="card-footer d-flex align-items-center justify-content-between">
            <p class="m-0 text-secondary small">
                Page <strong><?= $page ?></strong> sur <strong><?= $nbPages ?></strong>
            </p>
            <ul class="pagination m-0 ms-auto">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $urlFiltres(['page' => $page - 1]) ?>" tabindex="-1">
                        &lsaquo; Précédent
                    </a>
                </li>
                <?php foreach ($calculerPages($page, $nbPages) as $p): ?>
                    <?php if ($p === '...'): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php else: ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $urlFiltres(['page' => $p]) ?>"><?= $p ?></a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="page-item <?= $page >= $nbPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $urlFiltres(['page' => $page + 1]) ?>">
                        Suivant &rsaquo;
                    </a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- Modale de consultation des détails d'un log -->
<div class="modal modal-blur fade" id="modal-details-log" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mdl-titre">
                    <?= $icone('clock-rotate-left', 'me-2 text-primary') ?>Détails de l'opération #<span id="mdl-id"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="datagrid mb-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Date &amp; Heure</div>
                        <div class="datagrid-content fw-bold" id="mdl-date"></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Auteur</div>
                        <div class="datagrid-content fw-bold" id="mdl-auteur"></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Action</div>
                        <div class="datagrid-content" id="mdl-action"></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Module</div>
                        <div class="datagrid-content" id="mdl-module"></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Véhicule</div>
                        <div class="datagrid-content font-monospace fw-bold" id="mdl-immat"></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Adresse IP</div>
                        <div class="datagrid-content text-muted" id="mdl-ip"></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small">Description</label>
                    <div class="alert alert-light border py-2 px-3 mb-0" id="mdl-desc"></div>
                </div>

                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label text-secondary small mb-0">Données enregistrées / Différences</label>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btn-toggle-json" style="display: none;">
                            <?= $icone('eye', 'me-1') ?><span id="btn-toggle-json-texte">Voir JSON brut</span>
                        </button>
                    </div>
                    <div id="mdl-details-container">
                        <pre class="bg-dark text-white p-3 rounded" style="max-height: 320px; overflow-y: auto;" id="mdl-details-pre"></pre>
                    </div>
                    <div id="mdl-json-container" style="display: none;">
                        <pre class="bg-dark text-white p-3 rounded small mb-0" style="max-height: 320px; overflow-y: auto;" id="mdl-raw-json-pre"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modale de purge des logs anciens -->
<div class="modal modal-blur fade" id="modal-purger-journal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="post" action="<?= $e($base_url . '/admin/journal/purger') ?>">
                <?= Csrf::field() ?>
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <?= $icone('triangle-exclamation', 'me-2') ?>Purge manuelle du journal d'audit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <div class="d-flex">
                            <div><?= $icone('shield-halved', 'me-2 fs-2') ?></div>
                            <div>
                                <strong>Opération d'exception strictement manuelle :</strong><br>
                                Conformément aux principes fondamentaux de traçabilité et de conformité, la purge du journal d'audit <strong>ne peut pas et ne doit jamais être automatique</strong>. Aucune tâche planifiée (cron) ne supprime ces enregistrements.
                            </div>
                        </div>
                    </div>
                    <p class="mb-3">
                        Cette opération manuelle supprimera définitivement les événements les plus anciens du journal d'audit afin d'alléger la base de données.
                    </p>
                    <div class="mb-3">
                        <label class="form-label required" for="purge-jours">Conserver uniquement les logs des :</label>
                        <select class="form-select" id="purge-jours" name="jours">
                            <option value="90">90 derniers jours (3 mois)</option>
                            <option value="180">180 derniers jours (6 mois)</option>
                            <option value="365" selected>365 derniers jours (1 an)</option>
                            <option value="730">730 derniers jours (2 ans)</option>
                        </select>
                        <small class="form-hint">Les traces antérieures à cette période seront effacées irréversiblement.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <?= $icone('trash-can', 'me-1') ?>Confirmer la purge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalDetails = document.getElementById('modal-details-log');
    if (!modalDetails) return;

    var btnToggleJson = document.getElementById('btn-toggle-json');
    var btnToggleJsonTexte = document.getElementById('btn-toggle-json-texte');
    var detailsContainer = document.getElementById('mdl-details-container');
    var jsonContainer = document.getElementById('mdl-json-container');
    var rawJsonPre = document.getElementById('mdl-raw-json-pre');

    var isJsonView = false;
    btnToggleJson.addEventListener('click', function () {
        isJsonView = !isJsonView;
        if (isJsonView) {
            detailsContainer.style.display = 'none';
            jsonContainer.style.display = 'block';
            btnToggleJsonTexte.textContent = 'Voir tableau';
        } else {
            detailsContainer.style.display = 'block';
            jsonContainer.style.display = 'none';
            btnToggleJsonTexte.textContent = 'Voir JSON brut';
        }
    });

    var NOMS_CHAMPS = {
        'immatriculation': 'Immatriculation',
        'modele_id': 'Modèle ID',
        'modele_nom': 'Modèle',
        'marque_nom': 'Marque',
        'couleur': 'Couleur',
        'motorisation': 'Motorisation',
        'type_boite': 'Boîte de vitesses',
        'entite_id': 'Entité ID',
        'entite_nom': 'Entité',
        'loueur_id': 'Loueur ID',
        'loueur_nom': 'Loueur',
        'lieu_id': 'Lieu ID',
        'lieu_nom': 'Lieu d\'exploitation',
        'statut': 'Statut',
        'date_entree': 'Date d\'entrée',
        'duree_contrat': 'Durée contrat (mois)',
        'km_maxi': 'Kilométrage maxi',
        'kilometrage': 'Kilométrage',
        'sortie_angelus': 'Sortie Angelus',
        'date_sortie_prevue': 'Sortie prévisionnelle',
        'date_sortie_effective': 'Sortie effective',
        'sortie_reelle': 'Sortie réelle',
        'hayon': 'Équipé hayon',
        'temps_controle_hayon': 'Contrôle hayon (mois)',
        'date_dernier_controle_hayon': 'Dernier contrôle hayon',
        'date_prochain_controle_hayon': 'Prochain contrôle hayon',
        'commentaire': 'Commentaire',
        'cout_ht': 'Coût HT (€)',
        'cout_ttc': 'Coût TTC (€)',
        'date_intervention': 'Date d\'intervention',
        'prestataire': 'Prestataire / Garage',
        'type_intervention': 'Type d\'intervention',
        'date_incident': 'Date de l\'incident',
        'responsabilite': 'Responsabilité',
        'circonstances': 'Circonstances',
        'nom_document': 'Nom du document',
        'type_document': 'Type de document',
        'fichier': 'Fichier',
        'index_km': 'Relevé odomètre (km)',
        'periode': 'Période mensuelle',
        'nom': 'Nom',
        'email': 'Adresse email',
        'role': 'Rôle utilisateur',
        'actif': 'Compte actif',
        'deleted_at': 'Date d\'archivage'
    };

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatVal(v) {
        if (v === null || v === undefined || v === '') {
            return '<span class="text-muted fst-italic">—</span>';
        }
        if (v === true || v === 1 || v === '1') {
            return 'Oui';
        }
        if (v === false || v === 0 || v === '0') {
            return 'Non';
        }
        if (typeof v === 'object') {
            return '<pre class="mb-0 small bg-light p-1 rounded">' + escapeHtml(JSON.stringify(v, null, 2)) + '</pre>';
        }
        return escapeHtml(String(v));
    }

    modalDetails.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;

        document.getElementById('mdl-id').textContent = btn.getAttribute('data-log-id') || '';
        document.getElementById('mdl-date').textContent = btn.getAttribute('data-log-date') || '';
        document.getElementById('mdl-auteur').textContent = btn.getAttribute('data-log-auteur') || '';
        document.getElementById('mdl-action').textContent = btn.getAttribute('data-log-action') || '';
        document.getElementById('mdl-module').textContent = btn.getAttribute('data-log-module') || '';
        document.getElementById('mdl-immat').textContent = btn.getAttribute('data-log-immat') || '—';
        document.getElementById('mdl-ip').textContent = btn.getAttribute('data-log-ip') || '—';
        document.getElementById('mdl-desc').textContent = btn.getAttribute('data-log-desc') || '';

        // Reset view
        isJsonView = false;
        detailsContainer.style.display = 'block';
        jsonContainer.style.display = 'none';
        btnToggleJsonTexte.textContent = 'Voir JSON brut';

        var detailsRaw = (btn.getAttribute('data-log-details') || '').trim();
        if (!detailsRaw || detailsRaw === 'null' || detailsRaw === '[]' || detailsRaw === '{}') {
            btnToggleJson.style.display = 'none';
            detailsContainer.innerHTML = '<div class="alert alert-light border py-2 px-3 mb-0 small text-secondary">Aucune donnée détaillée enregistrée pour cette opération.</div>';
            return;
        }

        var parsed = null;
        try {
            parsed = JSON.parse(detailsRaw);
        } catch (e) {
            btnToggleJson.style.display = 'none';
            detailsContainer.innerHTML = '<div class="alert alert-light border py-2 px-3 mb-0 small">' + escapeHtml(detailsRaw) + '</div>';
            return;
        }

        btnToggleJson.style.display = 'inline-block';
        rawJsonPre.textContent = JSON.stringify(parsed, null, 2);

        // Case A: Diff with 'avant' and 'apres'
        if (parsed && typeof parsed === 'object' && ('avant' in parsed || 'apres' in parsed)) {
            var avant = (parsed.avant && typeof parsed.avant === 'object' && !Array.isArray(parsed.avant)) ? parsed.avant : {};
            var apres = (parsed.apres && typeof parsed.apres === 'object' && !Array.isArray(parsed.apres)) ? parsed.apres : {};
            var keys = Array.from(new Set([...Object.keys(avant), ...Object.keys(apres)]));

            if (parsed.aucun_changement || keys.length === 0) {
                var msg = parsed.message || 'Les données enregistrées sont strictement identiques à la version précédente (aucun champ n\'a été modifié).';
                detailsContainer.innerHTML =
                    '<div class="alert alert-info py-2 px-3 mb-0 d-flex align-items-center gap-2">' +
                        '<div class="text-info fw-bold"><i class="fa-solid fa-circle-info fa-lg"></i></div>' +
                        '<div>' +
                            '<strong>Aucune modification constatée :</strong>' +
                            '<div class="small text-secondary mt-1">' + escapeHtml(msg) + '</div>' +
                        '</div>' +
                    '</div>';
                return;
            }

            var html = '<div class="table-responsive border rounded">' +
                '<table class="table table-sm table-vcenter table-striped mb-0">' +
                '<thead class="table-light">' +
                    '<tr>' +
                        '<th style="width: 32%;">Champ</th>' +
                        '<th style="width: 34%;" class="text-danger">Avant</th>' +
                        '<th style="width: 34%;" class="text-success">Après</th>' +
                    '</tr>' +
                '</thead>' +
                '<tbody>';

            keys.forEach(function (k) {
                var libelle = NOMS_CHAMPS[k] || k;
                var vAv = avant[k];
                var vAp = apres[k];
                html += '<tr>' +
                    '<td>' +
                        '<div class="fw-bold small text-dark">' + escapeHtml(libelle) + '</div>' +
                        '<span class="font-monospace text-muted" style="font-size: 0.72rem;">' + escapeHtml(k) + '</span>' +
                    '</td>' +
                    '<td class="text-danger small bg-danger-lt">' + formatVal(vAv) + '</td>' +
                    '<td class="text-success small bg-success-lt fw-bold">' + formatVal(vAp) + '</td>' +
                '</tr>';
            });

            html += '</tbody></table></div>';
            detailsContainer.innerHTML = html;
            return;
        }

        // Case B: Simple key/value object
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            var keysObj = Object.keys(parsed);
            if (keysObj.length === 0) {
                detailsContainer.innerHTML = '<div class="alert alert-light border py-2 px-3 mb-0 small text-secondary">Objet vide.</div>';
                return;
            }

            var htmlObj = '<div class="table-responsive border rounded" style="max-height: 320px; overflow-y: auto;">' +
                '<table class="table table-sm table-vcenter table-striped mb-0">' +
                '<thead class="table-light">' +
                    '<tr>' +
                        '<th style="width: 35%;">Propriété</th>' +
                        '<th>Valeur</th>' +
                    '</tr>' +
                '</thead>' +
                '<tbody>';

            keysObj.forEach(function (k) {
                var libelle = NOMS_CHAMPS[k] || k;
                htmlObj += '<tr>' +
                    '<td>' +
                        '<div class="fw-bold small text-dark">' + escapeHtml(libelle) + '</div>' +
                        '<span class="font-monospace text-muted" style="font-size: 0.72rem;">' + escapeHtml(k) + '</span>' +
                    '</td>' +
                    '<td class="small">' + formatVal(parsed[k]) + '</td>' +
                '</tr>';
            });

            htmlObj += '</tbody></table></div>';
            detailsContainer.innerHTML = htmlObj;
            return;
        }

        // Case C: Array or other
        detailsContainer.innerHTML = '<pre class="bg-dark text-white p-3 rounded small mb-0">' + escapeHtml(JSON.stringify(parsed, null, 2)) + '</pre>';
    });
});
</script>

