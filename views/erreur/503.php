<?php
declare(strict_types=1);

/**
 * Page 503 : installation verrouillée mais configuration illisible.
 *
 * Cas volontairement distinct de l'assistant : rouvrir celui-ci risquerait
 * d'écraser une base déjà en service. L'utilisateur est invité à restaurer
 * config/database.php ou à supprimer le verrou après sauvegarde.
 *
 * @var string $motif
 * @var array $metadonnees
 */

use Core\Url;

$config = (require dirname(__DIR__, 2) . '/config/config.php');
$app    = $config['app'];
$e      = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Configuration illisible — <?= $e($app['nom']) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $e(Url::asset('img/flotteo.svg')) ?>">
    <link rel="stylesheet" href="<?= $e(Url::asset('tabler/css/tabler.min.css')) ?>">
    <link rel="stylesheet" href="<?= $e(Url::asset('css/flotteo.css')) ?>">
</head>
<body class="d-flex flex-column bg-light">
<div class="page page-center">
    <div class="container install-container py-4">
        <div class="text-center mb-4">
            <div class="empty">
                <div class="empty-header">503</div>
                <p class="empty-title">Installation incomplète</p>
                <p class="empty-subtitle text-secondary">
                    <?= $e($motif) ?>
                    L'assistant d'installation reste volontairement verrouillé afin de ne pas
                    écraser une base de données déjà en service.
                </p>
            </div>
        </div>

        <?php if ($metadonnees !== []): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Témoin d'installation</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-4">Application</dt><dd class="col-8"><?= $e((string) ($metadonnees['application'] ?? '—')) ?></dd>
                        <dt class="col-4">Version installée</dt><dd class="col-8"><?= $e((string) ($metadonnees['version'] ?? '—')) ?></dd>
                        <dt class="col-4">Date</dt><dd class="col-8"><?= $e((string) ($metadonnees['installee_le'] ?? '—')) ?></dd>
                    </dl>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Procédure de remise en état</h3></div>
            <div class="card-body">
                <ol class="mb-0">
                    <li>Sauvegarder la base de données et le dossier <code>public/uploads/</code>.</li>
                    <li>Restaurer <code>config/database.php</code> (permissions <code>0600</code>).</li>
                    <li>Vérifier la connectivité MySQL, puis recharger la page.</li>
                    <li>En dernier recours, et uniquement après sauvegarde, supprimer
                        <code>storage/install.lock</code> pour rouvrir l'assistant.</li>
                </ol>
                <div class="alert alert-warning mt-3 mb-0">
                    Ne supprimez le verrou qu'après avoir sauvegardé les données : l'assistant
                    refuse de réécrire une base non vide, mais une base vide serait réinitialisée.
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>