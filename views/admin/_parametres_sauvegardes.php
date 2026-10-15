<?php
declare(strict_types=1);

/**
 * Section « Sauvegardes » : durée de conservation, archivage local et partage réseau Samba.
 *
 * Trois formulaires distincts : les réglages, la sauvegarde immédiate et la rotation.
 *
 * Le mot de passe Samba n'est jamais renvoyé par le formulaire : un champ vide
 * conserve la valeur enregistrée, une case à cocher dédiée demande son
 * effacement. Même règle que la messagerie.
 *
 * @var array $valeurs, $sauvegardes
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;
use Models\Backup;
use Services\SambaClient;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

$v = static fn (string $cle): string => (string) ($valeurs[$cle] ?? '');

$historique      = $sauvegardes['historique'] ?? [];
$partageActif    = (bool) ($sauvegardes['partageActif'] ?? false);
$transport       = $sauvegardes['transport'] ?? null;
$invite          = (bool) ($sauvegardes['invite'] ?? true);
$alerte          = (string) ($sauvegardes['transportMessage'] ?? '');
$derniere        = $historique[0] ?? null;
?>

<!-- ======================= Exécution immédiate ======================= -->
<div class="card-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h4 class="mb-1"><?= $icone('box-archive', 'me-2') ?>Sauvegarde</h4>
            <p class="text-secondary mb-0">
                L'archive réunit l'export complet de la base (structure et données)
                et l'intégralité de <code>public/uploads/</code>, dans un seul fichier
                horodaté. <?php if ($derniere !== null): ?>
                    Dernière production : <strong><?= $e((string) $derniere['created_at']) ?></strong>.
                <?php else: ?>
                    Aucune sauvegarde n'a encore été produite.
                <?php endif; ?>
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#modal-aide-sauvegardes">
                <?= $icone('book', 'me-2') ?>Documentation & Guide
            </button>
            <form method="post" action="<?= $e($base_url . '/admin/sauvegardes/lancer') ?>" class="d-inline">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-primary">
                    <?= $icone('database', 'me-2') ?>Lancer une sauvegarde
                </button>
            </form>
            <form method="post" action="<?= $e($base_url . '/admin/sauvegardes/purger') ?>" class="d-inline">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline-secondary">
                    <?= $icone('trash-can', 'me-2') ?>Appliquer la rotation
                </button>
            </form>
        </div>
    </div>

    <div class="row row-cards mt-3">
        <div class="col-auto">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary small">Archives conservées</div>
                    <div class="h2 mb-0"><?= count($historique) ?></div>
                </div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary small">Volume occupé</div>
                    <div class="h2 mb-0"><?= $e(SambaClient::poids((int) ($sauvegardes['volume'] ?? 0))) ?></div>
                </div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary small">Conservation</div>
                    <div class="h2 mb-0"><?= (int) ($sauvegardes['retention'] ?? 30) ?> j</div>
                </div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary small">Répertoire local</div>
                    <div class="text-truncate font-monospace small" style="max-width:22rem"
                         title="storage/backups — hors de la racine servie par Apache">
                        storage/backups
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================== Réglages =========================== -->
<form id="form-sauvegardes-reglages" method="post" autocomplete="off"
      action="<?= $e($base_url . '/admin/parametres/sauvegardes/enregistrer') ?>">
    <?= Csrf::field() ?>

    <div class="card-body border-top">
        <h4 class="mb-3"><?= $icone('clock', 'me-2') ?>Rétention et rotation des archives</h4>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="sauvegarde_retention_jours">
                    Durée de conservation locale
                </label>
                <div class="input-group">
                    <input type="number" class="form-control" id="sauvegarde_retention_jours"
                           name="sauvegarde_retention_jours" min="<?= Backup::RETENTION_MIN ?>"
                           max="<?= Backup::RETENTION_MAX ?>" step="1" required
                           value="<?= $e($v('sauvegarde_retention_jours') !== '' ? $v('sauvegarde_retention_jours') : '30') ?>">
                    <span class="input-group-text">jours</span>
                </div>
                <p class="form-hint mb-0">
                    À chaque exécution planifiée, les archives locales plus anciennes que ce
                    délai sont supprimées du disque, et les lignes d'historique sans
                    fichier sont retirées.
                </p>
            </div>
        </div>
    </div>

    <div class="card-body border-top">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div class="d-flex align-items-center gap-2">
                <h4 class="mb-0"><?= $icone('network-wired', 'me-2') ?>Partage réseau Samba (SMB/CIFS)</h4>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-aide-sauvegardes" title="Documentation et aide à la configuration">
                    <?= $icone('circle-info', 'me-1') ?>Aide
                </button>
            </div>
            <label class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="sauvegarde_partage_actif"
                       name="sauvegarde_partage_actif" value="1" <?= $partageActif ? 'checked' : '' ?>
                       form="form-sauvegardes-reglages">
                <span class="form-check-label fw-bold">
                    <?= $partageActif ? 'Copie vers le partage activée' : 'Copie vers le partage désactivée' ?>
                </span>
            </label>
        </div>

        <p class="text-secondary small mb-3">
            Lors de chaque sauvegarde, une copie de l'archive est envoyée vers votre serveur de partage réseau (NAS, Windows Server, Linux Samba).
        </p>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="samba_hote">Hôte ou adresse IP</label>
                <input type="text" class="form-control font-monospace" id="samba_hote" name="samba_hote"
                       maxlength="190" placeholder="nas.entreprise.local ou 192.168.1.20"
                       form="form-sauvegardes-reglages" value="<?= $e($v('samba_hote')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="samba_partage">Nom du partage</label>
                <input type="text" class="form-control font-monospace" id="samba_partage" name="samba_partage"
                       maxlength="80" placeholder="sauvegardes"
                       form="form-sauvegardes-reglages" value="<?= $e($v('samba_partage')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="samba_repertoire">Répertoire cible ou Point de montage</label>
                <input type="text" class="form-control font-monospace" id="samba_repertoire" name="samba_repertoire"
                       maxlength="190" placeholder="flotteo/archives ou /mnt/sauvegardes"
                       form="form-sauvegardes-reglages" value="<?= $e($v('samba_repertoire')) ?>">
                <p class="form-hint mb-0">
                    Sous-dossier dans le partage distant (ex : <code>flotteo/archives</code>),
                    ou chemin absolu vers un point de montage local déjà monté par le système (ex : <code>/mnt/sauvegardes</code>).
                </p>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="samba_utilisateur">Utilisateur</label>
                <input type="text" class="form-control font-monospace" id="samba_utilisateur" name="samba_utilisateur"
                       maxlength="190" placeholder="vide = accès invité / anonyme"
                       form="form-sauvegardes-reglages" value="<?= $e($v('samba_utilisateur')) ?>">
                <p class="form-hint mb-0">
                    Un utilisateur vide demande un accès invité / anonyme : aucun
                    identifiant n'est transmis au partage.
                </p>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="samba_mot_de_passe">Mot de passe</label>
                <input type="password" class="form-control" id="samba_mot_de_passe" name="samba_mot_de_passe"
                       maxlength="190" autocomplete="new-password"
                       form="form-sauvegardes-reglages"
                       placeholder="<?= $v('samba_mot_de_passe') !== '' ? 'Mot de passe enregistré — laissez vide pour le conserver' : 'Aucun mot de passe enregistré' ?>">
                <label class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="samba_mot_de_passe_effacer"
                           name="samba_mot_de_passe_effacer" value="1" form="form-sauvegardes-reglages">
                    <span class="form-check-label">Effacer le mot de passe enregistré</span>
                </label>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="samba_domaine">Domaine / Workgroup</label>
                <input type="text" class="form-control font-monospace" id="samba_domaine" name="samba_domaine"
                       maxlength="190" placeholder="WORKGROUP"
                       form="form-sauvegardes-reglages" value="<?= $e($v('samba_domaine')) ?>">
                <p class="form-hint mb-0">Optionnel : laisser vide en accès invité ou si hors domaine Active Directory.</p>
            </div>
        </div>

        <div class="alert alert-<?= $transport !== null ? 'success' : 'warning' ?> mt-3 mb-0">
            <?= $icone($transport !== null ? 'circle-check' : 'triangle-exclamation', 'me-1') ?>
            <?php if ($transport === 'montage'): ?>
                Transport actif : <strong>point de montage local</strong>
                (<?= $e($invite ? 'accès invité' : 'accès identifié') ?>).
                La copie est une écriture directe dans le répertoire monté.
            <?php elseif ($transport === 'curl'): ?>
                Transport actif : <strong>cURL SMB natif</strong>
                (<?= $e($invite ? 'accès invité' : 'accès identifié') ?>).
                Transfert réseau direct vers le port 445/139 via le protocole SMB.
            <?php elseif ($transport === 'smbclient'): ?>
                Transport actif : <strong>binaire smbclient</strong>
                (<?= $e($invite ? 'accès invité' : 'accès identifié') ?>).
                Exécution sécurisée via fichier d'authentification temporaire.
            <?php else: ?>
                <strong>Partage Samba non disponible ou incomplet.</strong>
                <?= $e($alerte !== '' ? $alerte : 'Renseignez l\'hôte et le nom du partage, ou configurez un point de montage local.') ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <?= $icone('check', 'me-2') ?>Enregistrer les paramètres
        </button>
    </div>
</form>

<!-- ======================== Test de connexion ======================== -->
<div class="card-body border-top">
    <h4 class="mb-3"><?= $icone('server', 'me-2') ?>Épreuve du partage réseau</h4>
    <div class="d-flex flex-wrap align-items-center gap-3">
        <button type="button" class="btn btn-outline-primary" id="bouton-test-samba"
                data-url="<?= $e($base_url . '/admin/sauvegardes/tester') ?>">
            <?= $icone('network-wired', 'me-2') ?>Tester le partage Samba
        </button>
        <span class="text-secondary small">
            Les valeurs saisies sont éprouvées directement sans avoir besoin d'enregistrer d'abord.
        </span>
    </div>
    <div id="resultat-test-samba" class="mt-3"></div>
</div>

<!-- ============================ Historique ============================ -->
<div class="card-body border-top">
    <h4 class="mb-3"><?= $icone('file-lines', 'me-2') ?>Historique des sauvegardes</h4>

    <?php if ($historique === []): ?>
        <div class="empty-state">
            Aucune sauvegarde produite. Lancez une première sauvegarde pour établir l'historique.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Archive</th>
                    <th>Date</th>
                    <th class="w-1">Taille</th>
                    <th class="w-1">Contenu</th>
                    <th>Partage Samba</th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($historique as $s): ?>
                    <?php
                    $fichier = (string) $s['fichier'];
                    $present = \Services\BackupService::cheminArchive($fichier) !== null;
                    $statut  = (string) ($s['samba_statut'] ?? 'non_configure');
                    $couleur = ['reussi' => 'bg-green-lt', 'echec' => 'bg-red-lt', 'non_configure' => 'bg-secondary-lt'][$statut] ?? 'bg-secondary-lt';
                    $sambaMsg = trim((string) ($s['samba_message'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <span class="font-monospace fw-bold"><?= $e($fichier) ?></span>
                            <?php if (!$present): ?>
                                <div class="small text-danger">Fichier absent du stockage local</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= $e((string) $s['created_at']) ?></td>
                        <td class="text-end"><?= $e(SambaClient::poids((int) $s['taille'])) ?></td>
                        <td class="small text-secondary text-nowrap">
                            <?= (int) $s['tables_dump'] ?> tables · <?= (int) $s['fichiers_inclus'] ?> fichiers
                        </td>
                        <td>
                            <div>
                                <span class="badge <?= $couleur ?>" title="Externalisation Samba">
                                    SMB : <?= $e(Backup::SAMBA_STATUTS[$statut] ?? $statut) ?>
                                </span>
                            </div>
                            <?php if ($statut === 'echec' && $sambaMsg !== ''): ?>
                                <div class="small text-danger mt-1"><?= $e($sambaMsg) ?></div>
                            <?php elseif ($statut === 'non_configure' && $sambaMsg !== ''): ?>
                                <div class="small text-secondary mt-1"><?= $e($sambaMsg) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="cell-actions text-end">
                            <?php if ($present): ?>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= $e($base_url . '/admin/sauvegardes/telecharger?fichier=' . urlencode($fichier)) ?>"
                                   title="Télécharger l'archive sur ce poste">
                                     <?= $icone('download') ?>
                                </a>
                            <?php endif; ?>
                            <form method="post" action="<?= $e($base_url . '/admin/sauvegardes/supprimer') ?>"
                                  class="d-inline"
                                  data-confirmer="Supprimer définitivement l'archive <?= $e($fichier) ?> ? Elle ne sera plus téléchargeable depuis Flotteo.">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="btn btn-sm text-danger" title="Supprimer l'archive">
                                    <?= $icone('trash-can') ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card-body border-top no-print">
    <h4 class="mb-3"><?= $icone('clock', 'me-2') ?>Exécution planifiée</h4>
    <div class="row g-3">
        <div class="col-lg-7">
            <label class="form-label" for="cron-flotteo-backup">Tâche cron</label>
            <div class="input-group">
                <input type="text" class="form-control font-monospace text-break" id="cron-flotteo-backup"
                       readonly value="30 2 * * * /usr/bin/php <?= $e(dirname(__DIR__, 2)) ?>/bin/backup.php">
                <button type="button" class="btn btn-outline-secondary" data-copier="cron-flotteo-backup"
                        data-bs-toggle="tooltip" data-bs-title="Copier la commande"
                        aria-label="Copier la commande cron">
                    <?= $icone('file-lines') ?>
                </button>
            </div>
            <p class="form-hint mb-0">
                Chaque jour à 02 h 30 : production d'une archive, puis rotation.
                Le script journalise son déroulé et renvoie un code de sortie exploitable
                par le superviseur.
            </p>
        </div>
        <div class="col-lg-5">
            <label class="form-label" for="backup-restore">Restauration</label>
            <div class="input-group">
                <input type="text" class="form-control font-monospace text-break" id="backup-restore"
                       readonly value="unzip -o archive.zip -d /tmp && mysql -u root -p <base> < /tmp/donnees/flotteo.sql">
                <button type="button" class="btn btn-outline-secondary" data-copier="backup-restore"
                        data-bs-toggle="tooltip" data-bs-title="Copier la commande"
                        aria-label="Copier la commande de restauration">
                    <?= $icone('file-lines') ?>
                </button>
            </div>
            <p class="form-hint mb-0">
                L'archive contient <code>donnees/flotteo.sql</code> et <code>uploads/</code>.
                Restaurer les fichiers consiste à recopier ce second répertoire à la place
                de <code>public/uploads/</code>.
            </p>
        </div>
    </div>
</div>

<!-- ================= Modal : Documentation & Guide ================= -->
<div class="modal modal-blur fade" id="modal-aide-sauvegardes" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center">
                    <?= $icone('book', 'me-2') ?>Documentation & Guide d'Utilisation : Sauvegardes Flotteo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- 1. Vue d'ensemble -->
                    <div class="col-12">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <h4 class="card-title text-primary mb-0">
                                    <?= $icone('box-archive', 'me-2') ?>1. Principe et Contenu d'une Sauvegarde
                                </h4>
                            </div>
                            <div class="card-body">
                                <p>
                                    Le système de sauvegarde de <strong>Flotteo</strong> génère une archive compressée autonome au format <code>.zip</code>, nommée sous la forme <code>flotteo_backup_AAAA-MM-JJ_HHMM.zip</code>.
                                </p>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 bg-body-tertiary h-100">
                                            <div class="fw-bold mb-1 text-dark">
                                                <?= $icone('database', 'me-2 text-azure') ?>Base de données (<code>donnees/flotteo.sql</code>)
                                            </div>
                                            <div class="small text-secondary">
                                                Export transactionnel SQL intégral comprenant la structure (<code>CREATE TABLE</code>) et les données (<code>INSERT</code>) de toutes les tables applicatives : véhicules, entretiens, contrôles techniques, sinistres, pièces jointes, utilisateurs, alertes, relevés kilométriques et journal d'audit.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 bg-body-tertiary h-100">
                                            <div class="fw-bold mb-1 text-dark">
                                                <?= $icone('folder-open', 'me-2 text-warning') ?>Documents & Fichiers (<code>uploads/</code>)
                                            </div>
                                            <div class="small text-secondary">
                                                Copie conforme de l'ensemble des documents téléversés dans <code>public/uploads/</code> : cartes grises, procès-verbaux de contrôle technique, devis, factures, photos d'incidents et avatars.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-info mt-3 mb-0">
                                    <div class="d-flex">
                                        <div><?= $icone('shield-halved', 'me-2 fs-2') ?></div>
                                        <div>
                                            <strong>Stockage local sécurisé & Contrôle d'intégrité SHA-256 :</strong>
                                            Les archives sont générées dans <code>storage/backups/</code> (en dehors de la racine web Apache). Pour chaque archive, une empreinte cryptographique SHA-256 est calculée et mémorisée. Vous pouvez à tout moment cliquer sur l'icône <strong>Vérifier</strong> dans l'historique pour vous assurer que le fichier n'a pas été corrompu ou altéré.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Partage Réseau Samba -->
                    <div class="col-12">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <h4 class="card-title text-primary mb-0">
                                    <?= $icone('network-wired', 'me-2') ?>2. Configuration du Partage Réseau Samba (SMB/CIFS)
                                </h4>
                            </div>
                            <div class="card-body">
                                <p>
                                    L'externalisation réseau permet d'appliquer la règle de sauvegarde <strong>3-2-1</strong> en transférant automatiquement chaque archive vers un serveur distant (NAS QNAP, Synology, Windows Server ou serveur Linux Samba).
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:28%">Paramètre</th>
                                                <th>Description & Exemple</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="fw-bold">Hôte ou adresse IP</td>
                                                <td>
                                                    Adresse IP ou nom de machine du serveur de partage.<br>
                                                    <span class="text-secondary small">Exemple : <code>208.11.211.82</code> ou <code>nas-sauvegardes.entreprise.local</code></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">
                                                    Nom du partage
                                                    <span class="badge bg-primary-lt ms-1">Essentiel</span>
                                                </td>
                                                <td>
                                                    <strong>Nom exact du dossier partagé SMB racine</strong> configuré sur votre NAS ou serveur.<br>
                                                    <span class="text-danger small fw-semibold">Attention :</span> Ne mettez pas de barre oblique (<code>/</code> ou <code>\</code>).<br>
                                                    <span class="text-secondary small">Exemple : pour <code>\\208.11.211.82\Backup-Dev</code>, indiquez simplement <code>Backup-Dev</code>.</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Répertoire cible</td>
                                                <td>
                                                    Sous-dossier facultatif <em>à l'intérieur</em> du partage distant.<br>
                                                    <span class="text-success small fw-semibold">Recommandation :</span> Laissez ce champ <strong>vide</strong> pour déposer les sauvegardes directement à la racine du partage. Renseignez un nom (ex : <code>flotteo</code>) uniquement si vous souhaitez que les archives soient rangées dans un sous-dossier.
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Utilisateur & Mot de passe</td>
                                                <td>
                                                    Identifiants du compte utilisateur ayant les droits d'écriture sur le partage.<br>
                                                    <span class="text-secondary small">Pour des raisons de sécurité, le mot de passe n'est jamais réaffiché à l'écran. Laissez le champ vide lors des modifications pour conserver le mot de passe existant.</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Domaine / Workgroup</td>
                                                <td>
                                                    Groupe de travail Windows ou domaine Active Directory.<br>
                                                    <span class="text-secondary small">Exemple : <code>WORKGROUP</code> ou le nom de votre domaine d'entreprise (ex : <code>trelidis-dnsi</code>). Laissez vide si non applicable.</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="alert alert-warning mb-0">
                                    <div class="fw-bold mb-1">
                                        <?= $icone('triangle-exclamation', 'me-2') ?>Validation et Test de connexion
                                    </div>
                                    <ol class="mb-0 ps-3 small">
                                        <li>Basculez l'interrupteur <strong>« Copie vers le partage activée »</strong> en haut de la carte.</li>
                                        <li>Renseignez vos paramètres et cliquez d'abord sur <strong>« Enregistrer les réglages »</strong>.</li>
                                        <li>Cliquez sur le bouton <strong>« Tester le partage Samba »</strong> pour valider la communication immédiate avec votre NAS (le test utilise le protocole moderne SMB2/SMB3).</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Planification Cron -->
                    <div class="col-12">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <h4 class="card-title text-primary mb-0">
                                    <?= $icone('clock', 'me-2') ?>3. Planification Automatique Quotidienne (CRON)
                                </h4>
                            </div>
                            <div class="card-body">
                                <p>
                                    Pour assurer des sauvegardes automatiques sans intervention humaine, ajoutez la ligne suivante dans la table crontab du serveur (via <code>crontab -e</code> avec le compte utilisateur web ou root) :
                                </p>
                                <div class="bg-dark text-light p-3 rounded font-monospace small mb-3">
                                    30 2 * * * /usr/bin/php <?= $e(dirname(__DIR__, 2)) ?>/bin/backup.php > /dev/null 2>&1
                                </div>
                                <div class="fw-bold mb-2">Déroulement nocturne automatique (chaque jour à 02 h 30) :</div>
                                <div class="row g-2 small">
                                    <div class="col-md-3">
                                        <div class="p-2 border rounded bg-body-tertiary text-center h-100">
                                            <span class="badge bg-blue text-white mb-1">Étape 1</span>
                                            <div>Export SQL complet + compression ZIP des uploads.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-2 border rounded bg-body-tertiary text-center h-100">
                                            <span class="badge bg-green text-white mb-1">Étape 2</span>
                                            <div>Calcul de l'empreinte SHA-256 et inscription dans l'historique.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-2 border rounded bg-body-tertiary text-center h-100">
                                            <span class="badge bg-purple text-white mb-1">Étape 3</span>
                                            <div>Téléversement automatique sur le NAS Samba (si actif).</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-2 border rounded bg-body-tertiary text-center h-100">
                                            <span class="badge bg-orange text-white mb-1">Étape 4</span>
                                            <div>Purge locale des archives dépassant la durée de rétention.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Procédure de Restauration -->
                    <div class="col-12">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <h4 class="card-title text-primary mb-0">
                                    <?= $icone('wrench', 'me-2') ?>4. Procédure de Restauration Complète (Plan de Secours)
                                </h4>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">
                                    En cas de sinistre, de panne matérielle ou de réinstallation du serveur, suivez cette procédure pour restaurer l'application à l'identique :
                                </p>
                                <div class="list-group list-group-numbered">
                                    <div class="list-group-item">
                                        <div class="fw-bold">Récupérer l'archive de sauvegarde</div>
                                        <div class="text-secondary small">
                                            Téléchargez le fichier <code>.zip</code> depuis l'interface Flotteo (bouton Télécharger de l'historique), ou récupérez-le sur votre partage réseau NAS dans <code>\\208.11.211.82\Backup-Dev</code>.
                                        </div>
                                    </div>
                                    <div class="list-group-item">
                                        <div class="fw-bold">Décompresser l'archive dans un dossier temporaire</div>
                                        <div class="bg-dark text-light p-2 rounded font-monospace small mt-1">
                                            mkdir -p /tmp/flotteo_restore && unzip -o flotteo_backup_AAAA-MM-JJ_HHMM.zip -d /tmp/flotteo_restore/
                                        </div>
                                    </div>
                                    <div class="list-group-item">
                                        <div class="fw-bold">Restaurer la base de données MySQL / MariaDB</div>
                                        <div class="bg-dark text-light p-2 rounded font-monospace small mt-1">
                                            mysql -u root -p flotteo < /tmp/flotteo_restore/donnees/flotteo.sql
                                        </div>
                                    </div>
                                    <div class="list-group-item">
                                        <div class="fw-bold">Restaurer les documents et fichiers téléversés</div>
                                        <div class="bg-dark text-light p-2 rounded font-monospace small mt-1">
                                            cp -r /tmp/flotteo_restore/uploads/* /var/www/flotteo/public/uploads/
                                        </div>
                                    </div>
                                    <div class="list-group-item">
                                        <div class="fw-bold">Rétablir les droits d'accès web</div>
                                        <div class="bg-dark text-light p-2 rounded font-monospace small mt-1">
                                            chown -R www-data:www-data /var/www/flotteo/public/uploads /var/www/flotteo/storage
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-primary ms-auto" data-bs-dismiss="modal">
                    <?= $icone('check', 'me-2') ?>Fermer la documentation
                </button>
            </div>
        </div>
    </div>
</div>

