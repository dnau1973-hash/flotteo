<?php
declare(strict_types=1);

/**
 * Dossier d'un incident : détail et pièces jointes (constats, factures, photos).
 *
 * @var array $incident, $fichiers, $vehicules, $types, $statuts, $entites, $assurance_statuts
 * @var string $base_url
 */

use Core\Auth;
use Core\Csrf;
use Models\Incident;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$modifiable = Auth::canEditIncident($incident);
$peutSupprimer = Auth::canDeleteIncident();
$peutAjouterDocument = Auth::canUploadIncidentDocument();
$peutSupprimerDocument = Auth::canDeleteIncidentDocument();
$config = (require dirname(__DIR__, 2) . '/config/config.php');
$tailleMax = round($config['securite']['taille_max_upload'] / 1048576);
$isArchive = !empty($incident['deleted_at']);

$poids = static function (int $octets): string {
    return $octets < 1024
        ? $octets . ' o'
        : number_format($octets / 1024, 0, ',', ' ') . ' Ko';
};
?>

<?php if ($isArchive): ?>
    <div class="alert alert-warning d-flex align-items-center justify-content-between mb-3 shadow-xs">
        <div class="d-flex align-items-center gap-2">
            <span class="avatar bg-warning text-white rounded">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </span>
            <div>
                <strong class="d-block text-warning-emphasis">Incident archivé (supprimé logiquement)</strong>
                <span class="text-secondary small">
                    Ce dossier d'incident est masqué pour tous les profils non-administrateurs depuis le <?= date('d/m/Y à H:i', strtotime((string) $incident['deleted_at'])) ?>.
                </span>
            </div>
        </div>
        <?php if (Auth::can(Auth::ROLE_ADMIN)): ?>
            <div class="d-flex gap-2">
                <form method="post" action="<?= $e($base_url . '/incidents/restaurer') ?>">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $incident['id'] ?>">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i>Restaurer le dossier
                    </button>
                </form>
                <form method="post" action="<?= $e($base_url . '/incidents/supprimer-definitif') ?>"
                      data-confirmer="Attention : Supprimer DÉFINITIVEMENT cet incident et tous ses fichiers ? Cette action est irréversible.">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $incident['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash-can me-1"></i>Supprimer définitivement
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row row-deck row-cards">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Incident du <?= !empty($incident['date_incident']) ? date('d-m-Y', strtotime((string) $incident['date_incident'])) : '—' ?></h3>
                <div class="card-actions">
                    <span class="badge <?= $incident['statut'] === 'cloture' ? 'bg-green-lt' : ($incident['statut'] === 'en_traitement' ? 'bg-orange-lt' : 'bg-red-lt') ?> badge-statut">
                        <?= $e(Incident::STATUTS[(string) $incident['statut']] ?? (string) $incident['statut']) ?>
                    </span>
                    <?php if ($isArchive): ?>
                        <span class="badge bg-warning-lt text-warning ms-1">Archivé</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="datagrid mb-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Date de l'incident</div>
                        <div class="datagrid-content"><?= !empty($incident['date_incident']) ? date('d-m-Y', strtotime((string) $incident['date_incident'])) : '—' ?></div>
                    </div>
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
                        <div class="datagrid-title">Entité</div>
                        <div class="datagrid-content">
                            <span class="badge bg-blue-lt"><?= $e($incident['entite_nom'] ?: '—') ?></span>
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

                <h4 class="mb-2 mt-4"><i class="fa-solid fa-shield-halved text-primary me-1"></i> Assurance &amp; Prise en charge</h4>
                <div class="datagrid mb-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Prise en charge</div>
                        <div class="datagrid-content">
                            <?php if ($incident['assurance_statut'] === 'prise_en_charge'): ?>
                                <span class="badge bg-azure-lt">Prise en charge</span>
                            <?php elseif ($incident['assurance_statut'] === 'non_prise_en_charge'): ?>
                                <span class="badge bg-secondary-lt">Non prise en charge</span>
                            <?php else: ?>
                                <span class="text-secondary">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($incident['assurance_statut'] === 'prise_en_charge'): ?>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Remboursement attendu</div>
                            <div class="datagrid-content">
                                <?= (int) $incident['remboursement_attendu'] === 1 ? '<span class="text-warning fw-bold">Oui</span>' : 'Non' ?>
                            </div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Montant perçu de l'assurance</div>
                            <div class="datagrid-content fw-bold text-teal">
                                <?= $incident['montant_rembourse'] !== null ? number_format((float) $incident['montant_rembourse'], 2, ',', ' ') . ' €' : '—' ?>
                            </div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Montant de la franchise</div>
                            <div class="datagrid-content">
                                <?= $incident['montant_franchise'] !== null ? number_format((float) $incident['montant_franchise'], 2, ',', ' ') . ' €' : '—' ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Statut encaissement</div>
                        <div class="datagrid-content">
                            <?php if ((int) $incident['remboursement_percu'] === 1): ?>
                                <span class="badge bg-teal-lt"><i class="fa-solid fa-check me-1"></i>Remboursement perçu</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-lt">Non perçu</span>
                            <?php endif; ?>
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

            <div class="card-footer d-flex no-print">
                <?php if ($modifiable): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-incident">Modifier</button>
                <?php endif; ?>
                <a href="<?= $e($base_url . '/incidents') ?>" class="btn btn-outline-secondary <?= $modifiable ? 'ms-2' : '' ?>">Retour à la liste</a>
                <?php if ($isArchive): ?>
                    <?php if (Auth::can(Auth::ROLE_ADMIN)): ?>
                        <div class="ms-auto d-flex gap-2">
                            <form method="post" action="<?= $e($base_url . '/incidents/restaurer') ?>">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $incident['id'] ?>">
                                <button type="submit" class="btn btn-success">Restaurer le dossier</button>
                            </form>
                            <form method="post" action="<?= $e($base_url . '/incidents/supprimer-definitif') ?>"
                                  data-confirmer="Attention : Supprimer DÉFINITIVEMENT cet incident et l'ensemble de ses pièces jointes ? Cette action est IRRÉVERSIBLE.">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $incident['id'] ?>">
                                <button type="submit" class="btn btn-danger">Supprimer définitivement</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php elseif ($peutSupprimer): ?>
                    <form method="post" action="<?= $e($base_url . '/incidents/supprimer') ?>" class="ms-auto"
                          data-confirmer="Archiver ce dossier d'incident ? Il sera masqué pour les profils non-administrateurs.">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int) $incident['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger">Archiver le dossier</button>
                    </form>
                <?php endif; ?>
            </div>
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
                            <?php
                            $vignette    = Incident::aVignette($f);
                            $pdf         = (string) $f['mime'] === 'application/pdf';
                            $absente     = Incident::estImage($f) && !Incident::fichierPresent($f);
                            $apercu      = $base_url . '/incidents/fichier/apercu?id=' . (int) $f['id'];
                            $telecharger = $base_url . '/incidents/fichier/telecharger?id=' . (int) $f['id'];
                            $disponible  = $vignette || ($pdf && Incident::fichierPresent($f));
                            ?>
                            <li class="py-2">
                                <div class="d-flex align-items-center">
                                    <?php if ($vignette): ?>
                                        <a class="vignette-fichier me-2 document"
                                           href="<?= $e($apercu) ?>"
                                           data-document="<?= $e($apercu) ?>"
                                           data-nom="<?= $e((string) $f['nom_original']) ?>"
                                           data-type="image"
                                           data-mime="<?= $e(strtoupper((string) $f['mime'])) ?>"
                                           data-taille="<?= $e($poids((int) $f['taille'])) ?>"
                                           title="Ouvrir dans la visionneuse">
                                            <img src="<?= $e($apercu) ?>"
                                                 alt="<?= $e((string) $f['nom_original']) ?>"
                                                 width="56" height="56" loading="lazy" decoding="async">
                                        </a>
                                    <?php else: ?>
                                        <?php if ($pdf && Incident::fichierPresent($f)): ?>
                                            <a class="vignette-fichier vignette-fichier-icone me-2 document text-decoration-none"
                                               href="<?= $e($apercu) ?>"
                                               data-document="<?= $e($apercu) ?>"
                                               data-nom="<?= $e((string) $f['nom_original']) ?>"
                                               data-type="pdf"
                                               data-mime="PDF"
                                               data-taille="<?= $e($poids((int) $f['taille'])) ?>"
                                               title="Ouvrir dans la visionneuse">
                                                <i class="fa-solid fa-file-pdf text-danger"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="vignette-fichier vignette-fichier-icone me-2"
                                                  title="<?= $absente ? 'Fichier absent du stockage' : 'Aucun aperçu pour ce format' ?>"
                                                  aria-hidden="true">
                                                <i class="fa-solid <?= $pdf ? 'fa-file-pdf' : 'fa-file-lines' ?>"></i>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <div class="flex-fill">
                                        <?php if ($disponible): ?>
                                            <a href="<?= $e($apercu) ?>"
                                               class="text-reset d-block text-truncate document"
                                               data-document="<?= $e($apercu) ?>"
                                               data-nom="<?= $e((string) $f['nom_original']) ?>"
                                               data-type="<?= $pdf ? 'pdf' : 'image' ?>"
                                               data-mime="<?= $e(strtoupper((string) $f['mime'])) ?>"
                                               data-taille="<?= $e($poids((int) $f['taille'])) ?>"
                                               title="Ouvrir dans la visionneuse"><?= $e((string) $f['nom_original']) ?></a>
                                        <?php else: ?>
                                            <span class="d-block text-truncate text-secondary"
                                                  title="<?= $absente ? 'Fichier absent du stockage' : '' ?>">
                                                <?= $e((string) $f['nom_original']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <div class="small text-secondary">
                                            <?= $e(strtoupper((string) $f['mime'])) ?>
                                            &middot; <?= $e($poids((int) $f['taille'])) ?>
                                            &middot; <?= $e((string) $f['created_at']) ?>
                                        </div>
                                    </div>
                                    <a class="btn btn-sm btn-icon btn-outline-secondary ms-2"
                                       href="<?= $e($telecharger) ?>"
                                       title="Télécharger le fichier">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                        <span class="visually-hidden">Télécharger <?= $e((string) $f['nom_original']) ?></span>
                                    </a>
                                    <?php if ($peutSupprimerDocument): ?>
                                        <form method="post" action="<?= $e($base_url . '/incidents/fichier/supprimer') ?>"
                                              data-confirmer="Supprimer définitivement la pièce jointe ?">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-danger ms-1" title="Supprimer la pièce">
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

            <?php if ($peutAjouterDocument): ?>
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

<!-- Modale de visionneuse de document -->
<div class="modal modal-blur fade" id="modal-document" tabindex="-1" role="dialog" aria-hidden="true"
     aria-labelledby="titre-document">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="titre-document">Document</h5>
                <div class="modal-actions">
                    <a class="btn btn-sm btn-outline-primary" id="document-telecharger" href="#" download>
                        <i class="fa-solid fa-download me-1" aria-hidden="true"></i>
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
    <?php $this->partial('incidents/_formulaire', [
        'base_url'          => $base_url,
        'vehicules'         => $vehicules,
        'entites'           => $entites,
        'types'             => $types,
        'statuts'           => $statuts,
        'assurance_statuts' => $assurance_statuts,
        'vehicule_id'       => (int) $incident['vehicule_id'],
    ]); ?>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        /* Visionneuse de document */
        var modale = document.getElementById('modal-document');
        if (modale) {
            var image = document.getElementById('visionneuse-image');
            var cadre = document.getElementById('visionneuse-pdf');
            var titre = document.getElementById('titre-document');
            var meta  = document.getElementById('document-meta');
            var lien  = document.getElementById('document-telecharger');

            modale.addEventListener('show.bs.modal', function (evenement) {
                var declencheur = evenement.relatedTarget;
                if (!declencheur || !declencheur.hasAttribute('data-document')) { return; }

                var url  = declencheur.getAttribute('data-document');
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

            document.querySelectorAll('[data-document]').forEach(function (el) {
                el.addEventListener('click', function (evenement) {
                    if (evenement.ctrlKey || evenement.metaKey || evenement.shiftKey || evenement.button !== 0) {
                        return;
                    }
                    evenement.preventDefault();
                    bootstrap.Modal.getOrCreateInstance(modale).show(evenement.currentTarget);
                });
            });
        }

        /* Pré-remplissage de la modale d'édition avec l'incident courant */
        var modalIncident = document.getElementById('modal-incident');
        if (modalIncident) {
            modalIncident.addEventListener('show.bs.modal', function () {
                var idElem = document.getElementById('incident-id');
                if (idElem) idElem.value = '<?= (int) $incident['id'] ?>';
                var titreElem = document.getElementById('titre-incident');
                if (titreElem) titreElem.textContent = 'Modifier l\'incident';
                var vElem = document.getElementById('i_v_vehicule');
                if (vElem) vElem.value = '<?= (int) $incident['vehicule_id'] ?>';
                var entElem = document.getElementById('i_v_entite');
                if (entElem) entElem.value = <?= json_encode((string) ($incident['entite_id'] ?? '')) ?>;
                var tElem = document.getElementById('i_v_type');
                if (tElem) tElem.value = <?= json_encode((string) $incident['type']) ?>;
                var dElem = document.getElementById('i_v_date');
                if (dElem) dElem.value = <?= json_encode(!empty($incident['date_incident']) ? date('d-m-Y', strtotime((string) $incident['date_incident'])) : '') ?>;
                var lElem = document.getElementById('i_v_lieu');
                if (lElem) lElem.value = <?= json_encode((string) $incident['lieu']) ?>;
                var sElem = document.getElementById('i_v_statut');
                if (sElem) sElem.value = <?= json_encode((string) $incident['statut']) ?>;
                var rElem = document.getElementById('i_v_resp');
                if (rElem) rElem.checked = <?= (int) $incident['responsable'] === 1 ? 'true' : 'false' ?>;
                var imElem = document.getElementById('i_v_immat');
                if (imElem) imElem.checked = <?= (int) $incident['immatricule'] === 1 ? 'true' : 'false' ?>;
                var assElem = document.getElementById('i_v_assurance_statut');
                if (assElem) assElem.value = <?= json_encode((string) ($incident['assurance_statut'] ?? '')) ?>;
                var rembAttElem = document.getElementById('i_v_remb_attendu');
                if (rembAttElem) rembAttElem.checked = <?= (int) ($incident['remboursement_attendu'] ?? 0) === 1 ? 'true' : 'false' ?>;
                var montRembElem = document.getElementById('i_v_montant_rembourse');
                if (montRembElem) montRembElem.value = <?= json_encode($incident['montant_rembourse'] !== null ? (string) $incident['montant_rembourse'] : '') ?>;
                var montFranElem = document.getElementById('i_v_montant_franchise');
                if (montFranElem) montFranElem.value = <?= json_encode($incident['montant_franchise'] !== null ? (string) $incident['montant_franchise'] : '') ?>;
                var rembPercuElem = document.getElementById('i_v_remb_percu');
                if (rembPercuElem) rembPercuElem.checked = <?= (int) ($incident['remboursement_percu'] ?? 0) === 1 ? 'true' : 'false' ?>;
                var descElem = document.getElementById('i_v_description');
                if (descElem) descElem.value = <?= json_encode((string) $incident['description']) ?>;

                if (typeof window.actualiserBlocAssurance === 'function') {
                    window.actualiserBlocAssurance();
                }
            });
        }
    });
</script>
