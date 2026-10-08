<?php
declare(strict_types=1);

/**
 * Écran de connexion (hors layout applicatif).
 *
 * Toutes les URL passent par Core\Url : le préfixe de base est déduit de
 * l'emplacement réel du front controller, ce qui rend la page indépendante
 * du routage (`/login`, `/flotteo/login`) et de la configuration du vhost.
 */

use Core\Csrf;
use Core\Flash;
use Core\Url;

$config = (require dirname(__DIR__, 2) . '/config/config.php');
$base   = Url::baseRoute();
$flashes = Flash::pull();
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#206bc4">
    <title>Connexion — <?= htmlspecialchars($config['app']['nom'], ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="manifest" href="<?= htmlspecialchars(Url::manifest(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(Url::asset('img/flotteo.svg'), ENT_QUOTES, 'UTF-8') ?>">

    <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset('tabler/css/tabler.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset('css/flotteo.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="d-flex flex-column bg-light">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <span class="navbar-brand navbar-brand-autodark h1">
                <svg class="icon me-1 text-primary" viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor"
                     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 17h-2v-5l2 -5h14l2 5v5h-2"/><path d="M5 12h14"/>
                    <circle cx="7" cy="14" r="1"/><circle cx="17" cy="14" r="1"/>
                </svg>
                <?= htmlspecialchars($config['app']['nom'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <div class="text-secondary"><?= htmlspecialchars($config['app']['baseline'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert"
                 data-flotteo-toast="<?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>

        <form class="card card-md" method="post" action="<?= htmlspecialchars(Url::to('/login'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Connexion à l'application</h2>

                <?= Csrf::field() ?>

                <div class="mb-3">
                    <label class="form-label" for="email">Identifiant / Email</label>
                    <input type="email" class="form-control" id="email" name="email" required autofocus
                           placeholder="prenom.nom@societe.fr" autocomplete="username">
                </div>

                <div class="mb-2">
                    <label class="form-label" for="password">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" required
                           placeholder="••••••••" autocomplete="current-password">
                </div>

                <div class="mb-0">
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" id="afficher">
                        <span class="form-check-label">Afficher le mot de passe</span>
                    </label>
                </div>

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-4"/><path d="M10 17l5 -5l-5 -5"/>
                            <path d="M15 12H3"/>
                        </svg>
                        Se connecter
                    </button>
                </div>
            </div>
        </form>

        <p class="text-center text-secondary mt-3 small">
            Accès réservé — toute connexion est journalisée. Version <?= htmlspecialchars($config['app']['version'], ENT_QUOTES, 'UTF-8') ?>.
        </p>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="flotteo-toasts"></div>
<script src="<?= htmlspecialchars(Url::asset('tabler/js/tabler.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>window.FLOTTEO = { base: <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>, token: <?= json_encode(Csrf::token(), JSON_UNESCAPED_SLASHES) ?> };</script>
<script src="<?= htmlspecialchars(Url::asset('js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
    document.getElementById('afficher').addEventListener('change', function (e) {
        document.getElementById('password').type = e.target.checked ? 'text' : 'password';
    });
</script>
</body>
</html>
