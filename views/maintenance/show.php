<?php
declare(strict_types=1);

/**
 * Fiche d'une prestation d'entretien : détail du véhicule et pièces jointes.
 *
 * Les documents s'ouvrent dans une visionneuse modale plutôt que dans un
 * nouvel onglet : une facture se consulte, elle ne remplace pas la page, et
 * revenir à la fiche reste immédiat. Le téléchargement reste accessible, pour
 * l'utilisateur qui veut conserver le fichier.
 *
 * @var array $entretien, $vehicule, $fichiers, $types, $vehicules
 * @var int   $vehicule_id
 * @var string $base_url
 */

use Core\Auth;
use Core\Csrf;
use Models\Maintenance;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$modifiable = Auth::can(Auth::ROLE_MODIFICATION);
$config = (require dirname(__DIR__, 2) . '/config/config.php');
$tailleMax = round($config['securite']['taille_max_upload'] / 1048576);

/**
 * Taille lisible : les octets sous un kilo-octet, sinon les kilo-octets.
 *
 * Un devis de 469 octets affichait « 0 Ko », ce qui se lit comme un fichier
 * vide alors qu'il est parfaitement lisible.
 */
$poids = static function (int $octets): string {
    return $octets < 1024
        ? $octets . ' o'
        : number_format($octets / 1024, 0, ',', ' ') . ' Ko';
};

// `flotteo.css` est chargée par l'en commun ; seul le script de page l'est ici.
$this->useScript('entretien.js');
?>
<div class="row row-deck row-cards">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Prestation du <?= $e((string) $entretien['date_operation']) ?></h3>
                <div class="card-actions">
                    <span class="badge bg-blue-lt"><?= $e(ucfirst((string) $entretien['type_categorie'])) ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="datagrid mb-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Véhicule</div>
                        <div class="datagrid-content">
                            <a href="<?= $e($base_url . '/vehicules/voir?id=' . (int) $entretien['vehicule_id']) ?>"
                               class="text-reset fw-bold">
                                <?= $e((string) $entretien['immatriculation']) ?>
                            </a>
                            <div class="small text-secondary">
                                <?= $e($entretien['marque_nom'] . ' ' . $entretien['modele_nom']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Type de prestation</div>
                        <div class="datagrid-content"><?= $e((string) $entretien['type_libelle']) ?></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Kilométrage</div>
                        <div class="datagrid-content">
                            <?= $e(number_format((int) $entretien['kilometrage'], 0, ',', ' ')) ?> km
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Coût</div>
                        <div class="datagrid-content">
                            <?= $e(number_format((float) $entretien['cout_ht'], 2, ',', ' ')) ?> € HT
                            <div class="small text-secondary">
                                <?= $e(number_format((float) $entretien['cout_ttc'], 2, ',', ' ')) ?> € TTC
                            </div>
                        </div>
                    </div>
                </div>

                <h4 class="mb-2">Commentaire</h4>
                <p class="text-secondary mb-0">
                    <?= $entretien['commentaire'] !== null && $entretien['commentaire'] !== ''
                        ? nl2br($e((string) $entretien['commentaire']))
                        : 'Aucun commentaire renseigné.' ?>
                </p>
            </div>

            <?php if ($modifiable): ?>
                <div class="card-footer d-flex no-print">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-entretien"
                            data-edition-entretien
                            data-id="<?= (int) $entretien['id'] ?>"
                            data-vehicule="<?= (int) $entretien['vehicule_id'] ?>"
                            data-type="<?= (int) $entretien['type_intervention_id'] ?>"
                            data-date="<?= $e((string) $entretien['date_operation']) ?>"
                            data-km="<?= (int) $entretien['kilometrage'] ?>"
                            data-ht="<?= $e((string) $entretien['cout_ht']) ?>"
                            data-ttc="<?= $e((string) $entretien['cout_ttc']) ?>"
                            data-commentaire="<?= $e((string) $entretien['commentaire']) ?>">
                        Modifier
                    </button>
                    <a href="<?= $e($base_url . '/entretien') ?>" class="btn btn-outline-secondary ms-2">Retour à la liste</a>
                    <form method="post" action="<?= $e($base_url . '/entretien/supprimer') ?>" class="ms-auto"
                          data-confirmer="Supprimer cette prestation d'entretien et ses pièces jointes ? Cette action est irréversible.">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int) $entretien['id'] ?>">
                        <button type="submit" class="btn btn-danger">Supprimer la prestation</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="card-footer d-flex no-print">
                    <a href="<?= $e($base_url . '/entretien') ?>" class="btn btn-outline-secondary">Retour à la liste</a>
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
                        Aucune pièce jointe. Ajoutez la facture, le devis ou un rapport de contrôle.
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled liste-fichiers">
                        <?php foreach ($fichiers as $f): ?>
                            <?php
                            /*
                             * Deux lisibles aboutissent à l'icône : un format sans
                             * vignette et une image dont le fichier a disparu du
                             * stockage. Sans cette nuance, l'icône PDF s'afficherait
                             * sur une photographie manquante.
                             */
                            $vignette = Maintenance::aVignette($f);
                            $pdf      = (string) $f['mime'] === 'application/pdf';
                            $absente  = Maintenance::estImage($f) && !Maintenance::fichierPresent($f);
                            $apercu   = $base_url . '/entretien/fichier/apercu?id=' . (int) $f['id'];
                            ?>
                            <li class="py-2">
                                <div class="d-flex align-items-center">
                                    <?php if ($vignette): ?>
                                        <!--
                                            Le lien pointe vers l'aperçu, pas vers
                                            `telecharger` : `download()` force
                                            `Content-Disposition: attachment`, et une
                                            vignette ne s'affiche jamais depuis une
                                            réponse d'attachement. Le clic est repris
                                            par la visionneuse modale ; sans JavaScript,
                                            le lien reste une ouverture en ligne.
                                        -->
                                        <a class="vignette-fichier me-2 document"
                                           href="<?= $e($apercu) ?>"
                                           data-document="<?= $e($apercu) ?>"
                                           data-nom="<?= $e((string) $f['nom_original']) ?>"
                                           data-type="image"
                                           data-mime="<?= $e(strtoupper((string) $f['mime'])) ?>"
                                           data-taille="<?= $e($poids((int) $f['taille'])) ?>"
                                           title="Ouvrir dans la visionneuse">
                                            <img src="<?= $e($apercu) ?>" alt="<?= $e((string) $f['nom_original']) ?>"
                                                 width="56" height="56" loading="lazy" decoding="async">
                                        </a>
                                    <?php else: ?>
                                        <span class="vignette-fichier vignette-fichier-icone me-2"
                                              title="<?= $absente ? 'Fichier absent du stockage' : 'Aucun aperçu pour ce format' ?>"
                                              aria-hidden="true">
                                            <i class="fa-solid <?= $pdf ? 'fa-file-pdf' : 'fa-file-lines' ?>"></i>
                                        </span>
                                    <?php endif; ?>
                                    <div class="flex-fill">
                                        <?php if ($vignette || ($pdf && Maintenance::fichierPresent($f))): ?>
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
                                    <a class="btn btn-sm ms-2"
                                       href="<?= $e($base_url . '/entretien/fichier/telecharger?id=' . (int) $f['id']) ?>"
                                       title="Télécharger le fichier">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                        <span class="visually-hidden">Télécharger <?= $e((string) $f['nom_original']) ?></span>
                                    </a>
                                    <?php if ($modifiable): ?>
                                        <form method="post" action="<?= $e($base_url . '/entretien/fichier/supprimer') ?>"
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
                    <form method="post" action="<?= $e($base_url . '/entretien/fichier') ?>" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="maintenance_id" value="<?= (int) $entretien['id'] ?>">
                        <div class="mb-2">
                            <label class="form-label" for="piece">Ajouter une pièce jointe</label>
                            <input type="file" class="form-control" id="piece" name="fichier" required
                                   accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <small class="form-hint">
                                Facture, devis ou photo — PDF, JPG, PNG ou WEBP, <?= $e((string) $tailleMax) ?> Mo maximum.
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Téléverser la pièce</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($modifiable): ?>
    <?php $this->partial('maintenance/_formulaire', [
        'base_url'    => $base_url,
        'vehicules'   => $vehicules,
        'types'       => $types,
        'vehicule_id' => $vehicule_id,
    ]); ?>
<?php endif; ?>

<?php
/*
 * Visionneuse de document.
 *
 * Un seul conteneur sert les deux formats : une `<img>` pour les photographies,
 * un `<iframe>` pour les PDF, qui affiche la visionneuse du navigateur —
 * pagination, zoom, recherche texte, sans bibliothèque tierce. Embedder PDF.js
 * coûterait plusieurs centaines de kilo-octets pour redessiner ce que le
 * navigateur sait déjà faire ; l'application n'embarque que Tabler, ApexCharts
 * et FullCalendar.
 *
 * Le lien reste une URL réelle : sans JavaScript, le clic ouvre le document en
 * ligne. Le script reprend le clic seulement pour éviter de quitter la fiche.
 */
?>
<div class="modal modal-blur fade" id="modal-document" tabindex="-1" role="dialog" aria-hidden="true"
     aria-labelledby="titre-document">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="titre-document">Document</h5>
                <div class="modal-actions">
                    <a class="btn btn-sm" id="document-telecharger" href="#" download>
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
