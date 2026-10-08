<?php
declare(strict_types=1);

/**
 * Section « Mises à jour » : dépôt GitHub de référence et recherche des versions
 * publiées.
 *
 * Deux formulaires distincts, aucun imbriqué : l'enregistrement du dépôt, puis
 * la consultation. Ils suivent la séparation de la section Messagerie, où le test porte
 * sur les valeurs saisies et non sur celles enregistrées — l'administrateur peut
 * ainsi vérifier une correction avant de la valider.
 *
 * L'affichage ne fait **jamais** d'appel réseau : il relit le dernier relevé
 * mémorisé par `ParamController::verifierMiseAJour()`. Une section qui
 * interrogerait GitHub à chaque ouverture serait lente, consommerait le quota de
 * l'API à chaque affichage, et resterait bloquée quand le serveur n'a plus de
 * réseau sortant.
 *
 * @var array $valeurs, $misesAJour
 * @var string $base_url
 */

use Core\Csrf;
use Core\Icon;
use Services\GithubClient;

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$icone = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);

$v = static fn (string $cle): string => (string) ($valeurs[$cle] ?? '');

$versionInstallee = (string) ($misesAJour['version'] ?? '');
$depot            = (string) ($misesAJour['depot'] ?? '');
$releve           = ($misesAJour['releve'] ?? []);
$etat             = (string) ($misesAJour['etat'] ?? 'indetermine');
$message          = (string) ($misesAJour['message'] ?? '');

$succes      = (bool) ($releve['succes'] ?? false);
$etiquette   = (string) ($releve['etiquette'] ?? '');
$versionLue  = (string) ($releve['version'] ?? '');
$urlVersion  = (string) ($releve['url'] ?? '');
$publieLe    = (string) ($releve['publie_le'] ?? '');
$consulteLe  = (string) ($releve['consulte_le'] ?? '');
$raison      = (string) ($releve['message'] ?? '');
$source      = (string) ($releve['source'] ?? '');

// Un dépôt qui versionne par étiquettes sans créer de version donne un
// numéro comparable, mais sans date : l'écran doit le dire, sinon un « — »
// sous « Publiée le » se lirait comme une information manquante.
$parEtiquette = $source === 'etiquette';
$libelleVersion = $parEtiquette ? 'Étiquette la plus haute' : 'Version publiée';
?>

<!-- ====================== Version installée ====================== -->
<div class="card-body">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary small">Version installée</div>
                    <div class="h2 mb-0"><?= $e($versionInstallee !== '' ? $versionInstallee : '—') ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary small"><?= $e($libelleVersion) ?></div>
                    <div class="h2 mb-0">
                        <?php if ($succes && $versionLue !== ''): ?>
                            <?= $e($versionLue) ?>
                        <?php elseif ($succes && $etiquette !== ''): ?>
                            <span class="fs-5 font-monospace"><?= $e($etiquette) ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="text-secondary small">État</div>
                    <?php if ($etat === 'disponible'): ?>
                        <span class="badge bg-yellow-lt badge-statut"><?= $icone('arrow-up', 'me-1') ?>Mise à jour disponible</span>
                    <?php elseif ($etat === 'a_jour'): ?>
                        <span class="badge bg-green-lt badge-statut"><?= $icone('circle-check', 'me-1') ?>À jour</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-lt badge-statut"><?= $icone('circle-info', 'me-1') ?>Non déterminé</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== Dernier relevé mémorisé ==================== -->
<div class="card-body border-top">
    <h4 class="mb-3"><?= $icone('clock', 'me-2') ?>Dernier contrôle</h4>

    <?php if ($releve === []): ?>
        <div class="empty-state">
            <?= $icone('circle-info', 'icon mb-2') ?>
            Aucun contrôle n'a encore été effectué pour ce dépôt.
        </div>
    <?php elseif ($succes === false): ?>
        <div class="alert alert-warning mb-0" role="alert">
            <div class="fw-bold"><?= $icone('triangle-exclamation', 'me-1') ?>Contrôle sans résultat</div>
            <?= $e($raison !== '' ? $raison : 'Le dépôt n\'a pas pu être interrogé.') ?>
            <?php if ($consulteLe !== ''): ?>
                <div class="small mt-1">Tentative le <?= $e($consulteLe) ?>.</div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-secondary small">Étiquette relevée</div>
                <div class="font-monospace"><?= $e($etiquette !== '' ? $etiquette : '—') ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary small"><?= $parEtiquette ? 'Date de version' : 'Publiée le' ?></div>
                <div>
                    <?php if ($publieLe !== ''): ?>
                        <?= $e($publieLe) ?>
                    <?php elseif ($parEtiquette): ?>
                        <span class="text-secondary">Non indiquée par une étiquette</span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary small">Contrôlé le</div>
                <div><?= $e($consulteLe !== '' ? $consulteLe : '—') ?></div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary small">Dépôt interrogé</div>
                <div class="font-monospace text-truncate"><?= $e($releve['depot'] ?? $depot) ?></div>
            </div>
        </div>

        <?php if ($parEtiquette): ?>
            <div class="alert alert-info mt-3 mb-0" role="alert">
                <?= $icone('circle-info', 'me-1') ?>Ce dépôt ne publie aucune version formellement publiée.
                Le numéro affiché est celui de son <strong>étiquette de version la plus haute</strong> :
                il indique ce qui est le plus récent, sans date ni note de version associée.
            </div>
        <?php endif; ?>

        <?php if ($message !== '' && $etat === 'indetermine'): ?>
            <div class="alert alert-info mt-3 mb-0" role="alert">
                <?= $icone('circle-info', 'me-1') ?><?= $e($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($urlVersion !== ''): ?>
            <div class="mt-3">
                <a href="<?= $e($urlVersion) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                    <?= $icone('download', 'me-1') ?>Voir la publication sur GitHub
                </a>
                <span class="text-secondary small ms-2">La page s'ouvre dans un nouvel onglet.</span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- =========================== Réglages =========================== -->
<form id="form-mises-a-jour" method="post" autocomplete="off"
      action="<?= $e($base_url . '/admin/parametres/mises-a-jour/enregistrer') ?>">
    <?= Csrf::field() ?>

    <div class="card-body border-top">
        <h4 class="mb-3"><?= Icon::brands('github', 'me-2') ?>Dépôt de référence</h4>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="depot_github">Dépôt GitHub</label>
                <input type="text" class="form-control font-monospace" id="depot_github"
                       name="depot_github" maxlength="190"
                       placeholder="compte/depot"
                       value="<?= $e($v('depot_github')) ?>">
                <p class="form-hint mb-0">
                    Format <code>compte/dépôt</code>. Une adresse GitHub complète est
                    acceptée et ramenée à cette forme. Le dépôt doit être public :
                    Flotteo n'authentifie aucun appel.
                </p>
            </div>
        </div>
        <p class="form-hint mt-3 mb-0">
            Le contrôle est déclenché à la demande. L'API GitHub autorise
            <strong><?= GithubClient::quotaHoraire() ?></strong> requêtes par heure
            dans votre configuration — sans jeton d'authentification, la lecture des
            versions d'un dépôt public reste possible mais plafonnée à 60 par heure et
            par adresse IP.
        </p>
    </div>

    <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
        <button type="submit" class="btn btn-primary">
            <?= $icone('check', 'me-1') ?>Enregistrer le dépôt
        </button>
    </div>
</form>

<!-- ======================== Contrôle à la demande ======================== -->
<form method="post" autocomplete="off"
      action="<?= $e($base_url . '/admin/parametres/mises-a-jour/verifier') ?>">
    <?= Csrf::field() ?>

    <div class="card-body border-top">
        <p class="mb-0">
            Le contrôle porte sur le dépôt <strong>saisi ci-dessus</strong> s'il y en a
            un, sinon sur celui enregistré. Il interroge l'API GitHub et compare la
            version publiée à la version installée ; il ne modifie aucun code et
            n'installe rien.
        </p>
    </div>

    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="text-secondary small">
            <?php if (GithubClient::jetonConfigure()): ?>
                <?= $icone('lock', 'me-1') ?>
                Jeton d'authentification actif — la valeur n'est ni affichée, ni
                stockée en base, donc absente des archives de sauvegarde.
            <?php else: ?>
                <?= $icone('circle-info', 'me-1') ?>
                Accès anonyme. Pour élever le quota, renseignez un jeton dans
                <code>config/secrets.php</code> (clé <code>github_token</code>).
            <?php endif; ?>
        </span>
        <button type="submit" class="btn btn-outline-secondary">
            <?= $icone('magnifying-glass', 'me-1') ?>Rechercher les mises à jour
        </button>
    </div>
</form>