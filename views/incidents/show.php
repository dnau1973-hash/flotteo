<?php
declare(strict_types=1);

/**
 * Dossier d'un incident : détail et pièces jointes (constats, factures, photos).
 *
 * @var array $incident, $fichiers, $vehicules, $types, $statuts
 * @var string $base_url
 */

use Core\Auth;
use Core\Csrf;
use Models\Incident;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$modifiable = Auth::can(Auth::ROLE_MODIFICATION);
$config = (require dirname(__DIR__, 2) . '/config/config.php');
$tailleMax = round($config['securite']['taille_max_upload'] / 1048576);
?>

<div class="row row-deck row-cards">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Incident <?= $e((string) $incident['date_incident']) ?></h3>
                <div class="card-actions">
                    <span class="badge <?= $incident['statut'] === 'cloture' ? 'bg-green-lt' : ($incident['statut'] === 'en_traitement' ? 'bg-orange-lt' : 'bg-red-lt') ?> badge-statut">
                        <?= $e(Incident::STATUTS[(string) $incident['statut']] ?? (string) $incident['statut']) ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="datagrid mb-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Véhicule</div>
                        <div class="datagrid-content">
                            <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $incident['vehicule_id']) ?>" class="text-reset fw-bold">
                                <?= $e($incident['immatriculation']) ?>
                            </a>
                            <div class="small text-secondary"><?= $e($incident['marque_nom'] . ' ' . $incident['modele_nom']) ?></div>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Type d'incident</div>
                        <div class="datagrid-content"><?= $e(Incident::TYPES[(string) $incident['type']] ?? (string) $incident['type']) ?></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Lieu</div>
                        <div class="datagrid-content"><?= $e((string) ($incident['lieu'] ?: '—')) ?></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Responsabilité</div>
                        <div class="datagrid-content">
                            <?= (int) $incident['responsable'] === 1 ? 'Véhicule responsable' : 'Non responsable' ?>
                            &middot; <?= (int) $incident['immatricule'] === 1 ? 'Immatriculé' : 'Non immatriculé' ?>
                        </div>
                    </div>
                </div>

                <h4 class="mb-2">Descriptif</h4>
                <p class="text-secondary mb-0">
                    <?= $incident['description'] !== null && $incident['description'] !== ''
                        ? nl2br($e((string) $incident['description']))
                        : 'Aucun descriptif renseigné.' ?>
                </p>
            </div>

            <?php if ($modifiable): ?>
                <div class="card-footer d-flex no-print">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-incident">Modifier</button>
                    <a href="<?= $e($base_url . '/incidents') ?>" class="btn btn-outline-secondary ms-2">Retour à la liste</a>
                    <form method="post" action="<?= $e($base_url . '/incidents/supprimer') ?>" class="ms-auto"
                          data-confirmer="Supprimer cet incident et l'ensemble de ses pièces jointes ? Cette action est irréversible.">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int) $incident['id'] ?>">
                        <button type="submit" class="btn btn-danger">Supprimer le dossier</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pièces jointes</h3>
                <div class="card-actions">
                    <span class="badge bg-secondary-lt"><?= count($fichiers) ?></span>
                </div>
            </div>

            <div class="card-body">
                <?php if ($fichiers === []): ?>
                    <div class="empty-state">
                        Aucune pièce jointe. Ajoutez les constats amiables, factures ou photographies.
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled liste-fichiers">
                        <?php foreach ($fichiers as $f): ?>
                            <li class="py-2">
                                <div class="d-flex align-items-center">
                                    <svg class="icon text-primary me-2" viewBox="0 0 24 24" width="20" height="20" fill="none"
                                         stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
                                         aria-hidden="true">
                                        <path d="M14 3v5h5"/><path d="M19 21h-14a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h9l5 5v11a2 2 0 0 1 -2 2z"/>
                                    </svg>
                                    <div class="flex-fill">
                                        <a href="<?= $e($base_url . '/incidents/fichier/telecharger?id=' . (int) $f['id']) ?>"
                                           class="text-reset d-block text-truncate"><?= $e((string) $f['nom_original']) ?></a>
                                        <div class="small text-secondary">
                                            <?= $e(strtoupper((string) $f['mime'])) ?>
                                            &middot; <?= $e(number_format((int) $f['taille'] / 1024, 0, ',', ' ')) ?> Ko
                                            &middot; <?= $e((string) $f['created_at']) ?>
                                        </div>
                                    </div>
                                    <?php if ($modifiable): ?>
                                        <form method="post" action="<?= $e($base_url . '/incidents/fichier/supprimer') ?>"
                                              data-confirmer="Supprimer définitivement la pièce jointe ?">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                            <button type="submit" class="btn btn-sm text-danger" title="Supprimer la pièce">
                                                <svg class="icon" viewBox="0 0 24 24" width="16" height="16" fill="none"
                                                     stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                                                     stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M4 7h16"/><path d="M9 7v-3h6v3"/><path d="M6 7l1 13h10l1 -13"/>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php if ($modifiable): ?>
                <div class="card-footer no-print">
                    <form method="post" action="<?= $e($base_url . '/incidents/fichier') ?>" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="incident_id" value="<?= (int) $incident['id'] ?>">
                        <div class="mb-2">
                            <label class="form-label" for="piece">Ajouter une pièce jointe</label>
                            <input type="file" class="form-control" id="piece" name="fichier" required
                                   accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <small class="form-hint">PDF, JPG, PNG ou WEBP — <?= $e((string) $tailleMax) ?> Mo maximum.</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Téléverser la pièce</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($modifiable): ?>
    <?php $this->partial('incidents/_formulaire', [
        'base_url'   => $base_url,
        'vehicules'  => $vehicules,
        'types'      => $types,
        'statuts'    => $statuts,
        'vehicule_id' => (int) $incident['vehicule_id'],
    ]); ?>

    <script>
        /* Pré-remplissage de la modale d'édition avec l'incident courant. */
        document.addEventListener('DOMContentLoaded', function () {
            var element = document.getElementById('modal-incident');
            if (!element) { return; }
            element.addEventListener('show.bs.modal', function () {
                document.getElementById('incident-id').value = '<?= (int) $incident['id'] ?>';
                document.getElementById('titre-incident').textContent = 'Modifier l\'incident';
                document.getElementById('i_v_vehicule').value = '<?= (int) $incident['vehicule_id'] ?>';
                document.getElementById('i_v_type').value = <?= json_encode((string) $incident['type']) ?>;
                document.getElementById('i_v_date').value = <?= json_encode((string) $incident['date_incident']) ?>;
                document.getElementById('i_v_lieu').value = <?= json_encode((string) $incident['lieu']) ?>;
                document.getElementById('i_v_statut').value = <?= json_encode((string) $incident['statut']) ?>;
                document.getElementById('i_v_resp').checked = <?= (int) $incident['responsable'] === 1 ? 'true' : 'false' ?>;
                document.getElementById('i_v_immat').checked = <?= (int) $incident['immatricule'] === 1 ? 'true' : 'false' ?>;
                document.getElementById('i_v_description').value = <?= json_encode((string) $incident['description']) ?>;
            });
        });
    </script>
<?php endif; ?>
