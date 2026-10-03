<?php
declare(strict_types=1);

/**
 * Générateur des registres HTML autonomes (version Web compilée/affichable).
 *
 * Usage : php scripts/build_docs.php
 * Produit : docs/user_guide.html, docs/changelog.html, docs/features.html,
 *           docs/qa_recette.html
 *
 * Ces fichiers sont la « vue HTML » exigée par le §5 des règles : ils
 * s'ouvrent directement dans un navigateur, hors application. Le même contenu
 * est rendu **dans le layout de l'application** par `Controllers\DocsController`,
 * via le moteur partagé `Core\Markdown` — une seule implémentation, donc aucune
 * dérive possible entre les deux rendus. La description des quatre registres
 * est déclarée une seule fois dans `Models\Registre`.
 *
 * Le rendu s'appuie uniquement sur le socle PHP natif : aucune dépendance,
 * conformément à la règle « pas d'hallucination de dépendances ».
 */

$racine = dirname(__DIR__);
$sortie = static function (string $message): void {
    fwrite(STDOUT, $message . PHP_EOL);
};

require $racine . '/core/Url.php';
require $racine . '/core/Icon.php';
require $racine . '/core/Markdown.php';
require $racine . '/models/Registre.php';

use Core\Icon;
use Core\Markdown;
use Core\Url;
use Models\Registre;

foreach (Registre::tous() as $cle => $regle) {
    $source = $racine . '/docs/' . $regle['fichier'];
    if (!is_file($source)) {
        $sortie("Source manquante : {$regle['fichier']}");
        exit(1);
    }

    $markdown = (string) file_get_contents($source);
    $contenu  = Markdown::render($markdown);
    $fichier  = $racine . '/docs/' . $cle . '.html';
    file_put_contents($fichier, pageHtml($regle['titre'], $regle['icone'], $contenu, $cle));
    $sortie('Généré : docs/' . $cle . '.html (' . number_format(strlen($contenu)) . ' octets)');
}

exit(0);

// ---------------------------------------------------------------------------

/** Enveloppe le contenu converti dans un gabarit Tabler.io. */
function pageHtml(string $titre, string $icone, string $contenu, string $cle): string
{
    $base     = Url::base();
    $app      = 'Flotteo';
    $version  = (require dirname(__DIR__) . '/config/config.php')['app']['version'];
    $e        = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $nav      = '';

    foreach (Registre::tous() as $k => $r) {
        $actif = $k === $cle ? ' active' : '';
        $nav  .= '<li class="nav-item"><a class="nav-link' . $actif . '" href="'
            . $e($base . '/docs/' . $k) . '">'
            . Icon::solid($r['icone'], 'me-2') . $e($r['titre']) . '</a></li>';
    }

    $iconeHtml = Icon::solid($icone, 'me-2');

    return <<<HTML
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{$e($titre)} — {$e($app)}</title>
    <link rel="stylesheet" href="{$e($base)}/assets/tabler/css/tabler.min.css">
    <link rel="stylesheet" href="{$e($base)}/assets/css/flotteo.css">
</head>
<body>
<div class="page">
    <aside class="navbar-vertical navbar-expand-md" data-bs-theme="dark">
        <div class="container-fluid">
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="{$e($base)}/dashboard" class="text-white">{$e($app)}</a>
            </h1>
            <div class="navbar-collapse">
                <ul class="navbar-nav pt-lg-3">{$nav}</ul>
                <ul class="navbar-nav mt-3">
                    <li class="nav-item"><a class="nav-link" href="{$e($base)}/dashboard">Retour à l'application</a></li>
                </ul>
            </div>
        </div>
    </aside>
    <div class="page-wrapper">
        <header class="navbar navbar-expand-md d-print-none">
            <div class="container-xl"><h2 class="page-title">{$iconeHtml}{$e($titre)}</h2></div>
        </header>
        <div class="page-body">
            <div class="container-xl">
                <div class="card">
                    <div class="card-body markdown">
{$contenu}
                    </div>
                    <div class="card-footer text-secondary small">
                        Document généré depuis <code>{$e(Registre::obtenir($cle)['fichier'])}</code> — {$e($app)} v{$e($version)}.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{$e($base)}/assets/tabler/js/tabler.min.js"></script>
</body>
</html>
HTML;
}
