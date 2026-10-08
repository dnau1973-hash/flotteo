<?php
declare(strict_types=1);

/**
 * Section « Sauvegardes » : durée de conservation, partage réseau Samba, exécution
 * immédiate et historique des archives.
 *
 * Quatre formulaires distincts, aucun imbriqué : les réglages, la sauvegarde
 * immédiate, la rotation et la suppression d'un élément de l'historique. Chacun
 * porte son jeton CSRF et sa propre action, conformément à la règle « aucune
 * alerte native » : la suppression passe par la modale Tabler comme partout
 * ailleurs dans l'application.
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

$historique   = $sauvegardes['historique'] ?? [];
$partageActif = (bool) ($sauvegardes['partageActif'] ?? false);
$transport    = $sauvegardes['transport'] ?? null;
$invite       = (bool) ($sauvegardes['invite'] ?? true);
$alerte       = (string) ($sauvegardes['transportMessage'] ?? '');
$derniere     = $historique[0] ?? null;
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
                    <div class="text-secondary small">Répertoire</div>
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
        <h4 class="mb-3"><?= $icone('clock', 'me-2') ?>Rétention et rotation</h4>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="sauvegarde_retention_jours">
                    Durée de conservation
                </label>
                <div class="input-group">
                    <input type="number" class="form-control" id="sauvegarde_retention_jours"
                           name="sauvegarde_retention_jours" min="<?= Backup::RETENTION_MIN ?>"
                           max="<?= Backup::RETENTION_MAX ?>" step="1" required
                           value="<?= $e($v('sauvegarde_retention_jours') !== '' ? $v('sauvegarde_retention_jours') : '30') ?>">
                    <span class="input-group-text">jours</span>
                </div>
                <p class="form-hint mb-0">
                    À chaque exécution planifiée, les archives plus anciennes que ce
                    délai sont supprimées du disque, et les lignes d'historique sans
                    fichier sont retirées.
                </p>
            </div>

            <div class="col-md-6">
                <label class="form-label">Partage réseau</label>
                <label class="form-check form-switch mt-1">
                    <input class="form-check-input" type="checkbox" id="sauvegarde_partage_actif"
                           name="sauvegarde_partage_actif" value="1" <?= $partageActif ? 'checked' : '' ?>>
                    <span class="form-check-label">
                        <?= $partageActif ? 'Copie vers le partage activée' : 'Copie vers le partage désactivée' ?>
                    </span>
                </label>
                <p class="form-hint mb-0">
                    La désactivation n'efface aucun réglage : les archives restent
                    produites et conservées localement.
                </p>
            </div>
        </div>
    </div>

    <div class="card-body border-top">
        <h4 class="mb-3"><?= $icone('network-wired', 'me-2') ?>Partage Samba (SMB/CIFS)</h4>

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
                <label class="form-label" for="samba_repertoire">Répertoire cible dans le partage</label>
                <input type="text" class="form-control font-monospace" id="samba_repertoire" name="samba_repertoire"
                       maxlength="190" placeholder="flotteo/archives"
                       form="form-sauvegardes-reglages" value="<?= $e($v('samba_repertoire')) ?>">
                <p class="form-hint mb-0">
                    Chemin <em>relatif</em> au partage. Ce champ a deux lectures : le
                    sous-dossier de destination pour le binaire <code>smbclient</code>, et
                    le point de montage local si le partage y est déjà monté — dans ce
                    dernier cas, aucune authentification SMB n'est nécessaire depuis PHP.
                </p>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="samba_utilisateur">Utilisateur</label>
                <input type="text" class="form-control font-monospace" id="samba_utilisateur" name="samba_utilisateur"
                       maxlength="190" placeholder="vide = accès invité"
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
                <p class="form-hint mb-0">Exclusif du mode identifié : laisser vide en accès invité.</p>
            </div>
        </div>

        <div class="alert alert-<?= $transport !== null ? 'success' : 'warning' ?> mt-3 mb-0">
            <?= $icone($transport !== null ? 'circle-check' : 'triangle-exclamation', 'me-1') ?>
            <?php if ($transport === 'montage'): ?>
                Transport retenu : <strong>point de montage</strong>
                (<?= $e($invite ? 'accès invité' : 'accès identifié') ?>).
                La copie est une écriture de fichier ordinaire.
            <?php elseif ($transport === 'smbclient'): ?>
                Transport retenu : <strong>smbclient</strong>
                (<?= $e($invite ? 'accès invité' : 'accès identifié') ?>).
                Les identifiants sont transmis par un fichier à durée de vie éphémère,
                jamais par la ligne de commande.
            <?php else: ?>
                <strong>Aucun moyen d'accès au partage n'est disponible.</strong>
                <?= $e($alerte) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-end">
        <button type="submit" class="btn btn-primary">
            <?= $icone('check', 'me-2') ?>Enregistrer
        </button>
    </div>
</form>

<!-- ======================== Test de connexion ======================== -->
<div class="card-body border-top">
    <h4 class="mb-3"><?= $icone('server', 'me-2') ?>Épreuve du partage</h4>
    <div class="d-flex flex-wrap align-items-center gap-3">
        <button type="button" class="btn btn-outline-primary" id="bouton-test-samba"
                data-url="<?= $e($base_url . '/admin/sauvegardes/tester') ?>">
            <?= $icone('network-wired', 'me-2') ?>Tester la connexion Samba
        </button>
        <span class="text-secondary small">
            Les valeurs actuellement saisies sont éprouvées, sans les enregistrer :
            un partage peut ainsi être validé avant d'être persisté.
        </span>
    </div>
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
                    <th>Partage</th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($historique as $s): ?>
                    <?php
                    $fichier   = (string) $s['fichier'];
                    $present   = \Services\BackupService::cheminArchive($fichier) !== null;
                    $statut    = (string) $s['samba_statut'];
                    $couleur   = ['reussi' => 'bg-green-lt', 'echec' => 'bg-red-lt', 'non_configure' => 'bg-secondary-lt'][$statut] ?? 'bg-secondary-lt';
                    ?>
                    <tr>
                        <td>
                            <span class="font-monospace"><?= $e($fichier) ?></span>
                            <?php if (!$present): ?>
                                <div class="small text-secondary">Fichier absent du stockage</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= $e((string) $s['created_at']) ?></td>
                        <td class="text-end"><?= $e(SambaClient::poids((int) $s['taille'])) ?></td>
                        <td class="small text-secondary text-nowrap">
                            <?= (int) $s['tables_dump'] ?> tables · <?= (int) $s['fichiers_inclus'] ?> fichiers
                        </td>
                        <td>
                            <span class="badge <?= $couleur ?>"><?= $e(Backup::SAMBA_STATUTS[$statut] ?? $statut) ?></span>
                            <?php if ($statut === 'echec' && (string) $s['samba_message'] !== ''): ?>
                                <div class="small text-secondary"><?= $e((string) $s['samba_message']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="cell-actions text-end">
                            <?php if ($present): ?>
                                <a class="btn btn-sm"
                                   href="<?= $e($base_url . '/admin/sauvegardes/telecharger?fichier=' . urlencode($fichier)) ?>"
                                   title="Télécharger l'archive">
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