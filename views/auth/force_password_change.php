<?php
declare(strict_types=1);

/**
 * Écran imposé de renouvellement du mot de passe lors de la connexion.
 *
 * Interdit toute navigation tant que le mot de passe n'a pas été renouvelé.
 * Propose une jauge de complexité en direct et un générateur de mot de passe hautement sécurisé.
 */

use Core\Csrf;
use Core\Flash;
use Core\Icon;
use Core\Url;

$config  = (require dirname(__DIR__, 2) . '/config/config.php');
$base    = Url::baseRoute();
$flashes = Flash::pull();
$icone   = static fn (string $nom, string $classes = ''): string => Icon::solid($nom, $classes);
$user    = $utilisateur ?? Core\Auth::user();
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#206bc4">
    <title>Changement de mot de passe obligatoire — <?= htmlspecialchars($config['app']['nom'], ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="manifest" href="<?= htmlspecialchars(Url::manifest(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(Url::asset('img/flotteo.svg'), ENT_QUOTES, 'UTF-8') ?>">

    <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset('tabler/css/tabler.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset('css/fontawesome.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset('css/flotteo.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="d-flex flex-column bg-light">
<div class="page page-center">
    <div class="container container-tight py-4" style="max-width: 540px;">
        <div class="text-center mb-4">
            <span class="navbar-brand navbar-brand-autodark h1">
                <?= $icone('shield-halved', 'text-warning me-2') ?>
                <?= htmlspecialchars($config['app']['nom'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <div class="text-secondary"><?= htmlspecialchars($config['app']['baseline'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
                <?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>

        <div class="card card-md shadow-sm">
            <div class="card-status-top bg-warning"></div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <span class="avatar avatar-lg bg-warning-lt rounded-circle mb-2">
                        <?= $icone('key', 'fs-2 text-warning') ?>
                    </span>
                    <h2 class="card-title mb-1">Renouvellement obligatoire du mot de passe</h2>
                    <?php if (!empty($status_mdp['expire_par_delai'])): ?>
                        <p class="text-secondary small">
                            Votre mot de passe a dépassé la durée maximale de validité de <strong>6 mois</strong>. Vous devez définir un nouveau mot de passe sécurisé pour continuer à utiliser votre compte
                            <strong><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>.
                        </p>
                    <?php else: ?>
                        <p class="text-secondary small">
                            Pour des raisons de sécurité, la mise à jour de votre mot de passe est requise pour continuer à utiliser votre compte
                            <strong><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Boîte générateur de mot de passe hautement sécurisé -->
                <div class="card bg-light-lt border mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold small text-primary">
                                <?= $icone('shield-halved', 'me-1') ?>Générateur de mot de passe fort
                            </span>
                            <span class="badge bg-primary-lt">Recommandé</span>
                        </div>
                        <p class="text-secondary small mb-2">
                            Générez un mot de passe robuste de 16 caractères répondant instantanément à toutes les exigences de sécurité.
                        </p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary flex-fill" id="btn-generer-mdp">
                                <?= $icone('arrow-right-arrow-left', 'me-1') ?>Proposer un mot de passe
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-copier-mdp" disabled>
                                <?= $icone('copy', 'me-1') ?>Copier
                            </button>
                        </div>
                    </div>
                </div>

                <form method="post" action="<?= htmlspecialchars(Url::to('/changer-mot-de-passe-requis'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" id="form-force-pwd">
                    <?= Csrf::field() ?>

                    <div class="mb-3">
                        <label class="form-label required" for="password_actuel">Mot de passe actuel</label>
                        <div class="input-group input-group-flat">
                            <input type="password" class="form-control" id="password_actuel" name="password_actuel" required
                                   placeholder="Votre mot de passe actuel" autocomplete="current-password">
                            <span class="input-group-text">
                                <button type="button" class="link-secondary text-decoration-none border-0 bg-transparent p-0"
                                        data-toggle-password="password_actuel" title="Afficher/masquer" tabindex="-1">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="password_nouveau">Nouveau mot de passe</label>
                        <div class="input-group input-group-flat">
                            <input type="password" class="form-control" id="password_nouveau" name="password_nouveau" required
                                   minlength="10" placeholder="Nouveau mot de passe fort" autocomplete="new-password">
                            <span class="input-group-text">
                                <button type="button" class="link-secondary text-decoration-none border-0 bg-transparent p-0"
                                        data-toggle-password="password_nouveau" title="Afficher/masquer" tabindex="-1">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </span>
                        </div>

                        <!-- Jauge de complexité -->
                        <div class="mt-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-secondary">Force du mot de passe :</span>
                                <span id="force-texte" class="small text-danger fw-bold">Aucun</span>
                            </div>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-danger" id="force-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <!-- Critères de complexité -->
                        <div class="mt-3 p-2 bg-body-tertiary rounded border border-dashed small">
                            <div class="fw-bold mb-1 text-secondary">Critères requis :</div>
                            <ul class="list-unstyled mb-0 row g-1">
                                <li class="col-sm-6 text-secondary" id="crit-longueur">
                                    <i class="critere-icon fa-regular fa-circle text-secondary me-1"></i>10 caractères minimum
                                </li>
                                <li class="col-sm-6 text-secondary" id="crit-minuscule">
                                    <i class="critere-icon fa-regular fa-circle text-secondary me-1"></i>Au moins une minuscule (a-z)
                                </li>
                                <li class="col-sm-6 text-secondary" id="crit-majuscule">
                                    <i class="critere-icon fa-regular fa-circle text-secondary me-1"></i>Au moins une majuscule (A-Z)
                                </li>
                                <li class="col-sm-6 text-secondary" id="crit-chiffre">
                                    <i class="critere-icon fa-regular fa-circle text-secondary me-1"></i>Au moins un chiffre (0-9)
                                </li>
                                <li class="col-sm-12 text-secondary" id="crit-special">
                                    <i class="critere-icon fa-regular fa-circle text-secondary me-1"></i>Au moins un caractère spécial (!@#$%...)
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="password_confirmation">Confirmer le nouveau mot de passe</label>
                        <div class="input-group input-group-flat">
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required
                                   minlength="10" placeholder="Retapez le nouveau mot de passe" autocomplete="new-password">
                            <span class="input-group-text">
                                <button type="button" class="link-secondary text-decoration-none border-0 bg-transparent p-0"
                                        data-toggle-password="password_confirmation" title="Afficher/masquer" tabindex="-1">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </span>
                        </div>
                        <div class="mt-1 small text-secondary" id="crit-match">
                            <i class="critere-icon fa-regular fa-circle text-secondary me-1"></i>Les deux mots de passe doivent correspondre
                        </div>
                    </div>

                    <div class="form-footer mt-4">
                        <button type="submit" class="btn btn-primary w-100" id="btn-soumettre-mdp" disabled>
                            <?= $icone('check', 'me-1') ?>Enregistrer le nouveau mot de passe
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-transparent py-2 text-center">
                <form method="post" action="<?= htmlspecialchars(Url::to('/logout'), ENT_QUOTES, 'UTF-8') ?>" class="d-inline">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-link link-secondary text-decoration-none btn-sm">
                        <?= $icone('right-from-bracket', 'me-1') ?>Se déconnecter et quitter
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-secondary mt-3 small">
            Flotteo — Version <?= htmlspecialchars($config['app']['version'], ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
</div>

<script src="<?= htmlspecialchars(Url::asset('tabler/js/tabler.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(Url::asset('js/password-strength.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>

