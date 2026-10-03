<?php
declare(strict_types=1);

/**
 * Page d'erreur générique 500.
 * Aucun détail technique n'est exposé : le trace est journalisée côté serveur.
 */

$config = (require dirname(__DIR__, 2) . '/config/config.php');

use Core\Url;
$base   = Url::base();
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Erreur technique — <?= htmlspecialchars($config['app']['nom'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset('tabler/css/tabler.min.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="d-flex flex-column bg-light">
<div class="page page-center">
    <div class="container container-tight text-center">
        <div class="empty">
            <div class="empty-header">500</div>
            <p class="empty-title">Erreur technique</p>
            <p class="empty-subtitle text-secondary">
                L'opération n'a pas pu aboutir. L'incident a été journalisé, contactez l'administrateur si le problème persiste.
            </p>
            <div class="empty-action">
                <a href="<?= htmlspecialchars(Url::to('/dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">
                    Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
