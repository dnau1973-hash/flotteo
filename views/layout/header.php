<?php
declare(strict_types=1);

/**
 * En-tête commun : <head> et bandeau supérieur de navigation.
 *
 * La barre s'étend sur 100 % de la largeur (`container-fluid`), l'identité est à
 * gauche, les modules sont dans la même barre horizontale et le menu utilisateur
 * avec la déconnexion à droite. Toutes les URL sont produites par Core\Url.
 *
 * Icônes : Font Awesome Free auto-hébergé, exclusively via les classes
 * `fa-solid fa-…` / `fa-brands fa-…`. Aucun SVG inline, aucune police distante.
 *
 * @var string|null $pageTitre  titre affiché dans la barre et l'onglet
 */

use Core\Flash;
use Core\Url;

$app     = (require dirname(__DIR__, 2) . '/config/config.php')['app'];
$base    = Url::baseRoute();
$flashes = Flash::pull();
$user    = Core\Auth::user();

/**
 * Chemin de la requête normalisé, préfixe de base retiré par Core\Router : la
 * détection reste correcte quand Flotteo est installé dans un sous-dossier.
 */
$cheminActif = Core\Router::currentPath();

/** Libellé lisible de la page courante, résolu sur le chemin complet. */
$titresChemins = [
    '/'                           => 'Tableau de bord',
    '/dashboard'                  => 'Tableau de bord',
    '/agenda'                     => 'Agenda',
    '/vehicules'                  => 'Véhicules',
    '/entretien'                  => 'Révisions & entretien',
    '/incidents'                  => 'Incidents & sinistres',
    '/echeances'                  => 'Échéances',
    '/kilometrage'                => 'Relevés kilométriques',
    '/admin'                      => 'Administration',
    '/admin/parametres'           => 'Paramètres système',
    '/admin/parametres/messagerie' => 'Paramètres — Messagerie',
    '/admin/parametres/alertes'   => 'Paramètres — Alertes',
    '/admin/parametres/kilometrage' => 'Paramètres — Relevés kilométriques',
    '/admin/utilisateurs'         => 'Utilisateurs',
    '/admin/dictionnaires'        => 'Dictionnaires',
    '/docs/user_guide'            => 'Guide utilisateur',
    '/docs/changelog'             => 'Journal des modifications',
    '/docs/features'              => 'Fonctionnalités',
    '/docs/qa_recette'            => 'Recette QA',
    '/profil'                     => 'Mon profil',
];
$pageTitre = $pageTitre ?? ($titresChemins[$cheminActif]
    ?? $titresChemins['/' . strtok(ltrim($cheminActif, '/'), '/')]
    ?? $app['nom']);

/**
 * Raccourci de vue : Core\Icon centralise la fabrication des icônes Font Awesome
 * et la validation du sous-ensemble embarqué.
 *
 * @var callable $icone
 */
$icone = static fn (string $nom, string $classes = ''): string => Core\Icon::solid($nom, $classes);

/** Entrées de la barre. `role` filtre l'affichage selon les droits de l'utilisateur. */
$modules = [
    ['cle' => 'dashboard', 'url' => '/dashboard',        'libelle' => 'Tableau de bord',      'icone' => 'gauge-high',           'role' => 'lecture'],
    ['cle' => 'agenda',    'url' => '/agenda',            'libelle' => 'Agenda',               'icone' => 'clock',                 'role' => 'lecture'],
    ['cle' => 'vehicules',  'url' => '/vehicules',         'libelle' => 'Véhicules',           'icone' => 'car',                   'role' => 'lecture'],
    ['cle' => 'kilometrage', 'url' => '/kilometrage',      'libelle' => 'Kilométrage',         'icone' => 'chart-line',           'role' => 'lecture'],
    ['cle' => 'entretien',  'url' => '/entretien',         'libelle' => 'Révisions',           'icone' => 'screwdriver-wrench',   'role' => 'lecture'],
    ['cle' => 'incidents',  'url' => '/incidents',         'libelle' => 'Incidents',           'icone' => 'triangle-exclamation', 'role' => 'lecture'],
    ['cle' => 'echeances',  'url' => '/echeances',         'libelle' => 'Échéances',           'icone' => 'calendar-days',        'role' => 'lecture'],
    ['cle' => 'referentiels', 'url' => '/admin/dictionnaires', 'libelle' => 'Référentiels',    'icone' => 'list',                  'role' => 'admin'],
    ['cle' => 'parametres', 'url' => '/admin/parametres',  'libelle' => 'Paramètres',          'icone' => 'sliders',              'role' => 'admin'],
    ['cle' => 'utilisateurs', 'url' => '/admin/utilisateurs', 'libelle' => 'Utilisateurs',      'icone' => 'users',                'role' => 'admin'],
];

/** Les entrées réellement accessibles à l'utilisateur connecté. */
$modulesVisibles = array_values(array_filter(
    $modules,
    static fn (array $m): bool => $m['role'] !== 'admin' || Core\Auth::can(Core\Auth::ROLE_ADMIN)
));

/**
 * Entrée active de la barre.
 *
 * La correspondance porte sur le CHEMIN COMPLET, jamais sur un seul segment :
 * `/admin/parametres` et `/admin/utilisateurs` partagent le segment `admin`, si
 * bien qu'une comparaison segment par segment ne pouvait activer aucun des deux.
 * Un sous-chemin reste rattaché à son module (`/vehicules/voir` garde
 * « Véhicules » actif) ; en cas de chevauchement d'URL, la plus longue l'emporte.
 */
$cleActive      = null;
$longueurActive = -1;
foreach ($modulesVisibles as $module) {
    $urlModule = rtrim($module['url'], '/');
    $correspond = $cheminActif === $urlModule
        || ($cheminActif === '/' && $urlModule === '/dashboard')
        || str_starts_with($cheminActif, $urlModule . '/');

    if ($correspond && strlen($urlModule) > $longueurActive) {
        $cleActive      = $module['cle'];
        $longueurActive = strlen($urlModule);
    }
}
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#206bc4">
    <title><?= htmlspecialchars($pageTitre, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($app['nom'], ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="manifest" href="<?= htmlspecialchars(Url::manifest(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(Url::asset('img/flotteo.svg'), ENT_QUOTES, 'UTF-8') ?>">

    <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset('tabler/css/tabler.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <!-- Font Awesome : polices woff2 locales, aucun CDN. -->
    <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset('css/fontawesome.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset('css/flotteo.css'), ENT_QUOTES, 'UTF-8') ?>">

    <?php foreach (($styles ?? []) as $feuille): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(Url::asset($feuille), ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="page">

    <!-- ============ Bandeau supérieur — 100 % de la largeur ============== -->
    <!-- Barre horizontale classique Tabler : `container-fluid`, donc aucune
         limite de largeur fixe. Pas de `navbar-vertical`, qui transformerait
         cette barre en colonne latérale. Le padding latéral `px-4 px-lg-5` est
         celui du corps de page : sans lui, l'identité et la première entrée de
         menu seraient désalignées du contenu des cartes.

         `navbar-expand-xl` et non `-lg` : les sept modules, l'identité et le
         menu utilisateur exigeaient environ 1130 px, que la pleine largeur ne
         pouvait plus garantir entre 992 et 1200 px — d'où un défilement
         horizontal de toute la page. Le liseré d'onglet actif suit le point de
         rupture, via `.navbar-expand-xl .nav-item.active:after` à partir de
         1200 px. En dessous, le menu est replié derrière le bouton et Tabler ne
         dessine aucun repère par `:after` — la règle
         `.navbar-collapse .nav-item.active:after` ne fixe pas de `content` et ne
         produit donc rien. C'est le comportement de la barre avant ce changement,
         sous 992 px : le module courant y reste identifiable par son gras, que
         porte `.barre-superieure .navbar-nav .nav-link.active` (600 contre 400).
         Vérifié au rendu : filet de 2 px de 1200 px à 1920 px, gras 600 dans le
         menu replié, aucun débordement de 500 px à 1920 px. -->
    <header class="navbar navbar-expand-xl d-print-none barre-superieure">
        <div class="container-fluid px-4 px-lg-5">
            <div class="row flex-nowrap align-items-center w-100">

                <!-- 1. Identité, à gauche -->
                <div class="col-auto d-flex align-items-center">
                    <a class="navbar-brand pe-0 pe-lg-3 me-3"
                       href="<?= htmlspecialchars(Url::to('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= $icone('car', 'text-primary me-2') ?>
                        <span class="navbar-brand-text"><?= htmlspecialchars($app['nom'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                    <button class="navbar-toggler ms-0" type="button"
                            data-bs-toggle="collapse" data-bs-target="#menu-modules"
                            aria-controls="menu-modules" aria-expanded="false"
                            aria-label="Afficher les modules">
                        <?= $icone('bars') ?>
                    </button>
                </div>

                <!-- 2. Modules, dans la même barre horizontale -->
                <div class="collapse navbar-collapse flex-grow-1" id="menu-modules">
                    <ul class="navbar-nav">
                        <?php foreach ($modulesVisibles as $module): ?>
                            <li class="nav-item<?= $cleActive === $module['cle'] ? ' active' : '' ?>">
                                <a class="nav-link<?= $cleActive === $module['cle'] ? ' active' : '' ?>"
                                   href="<?= htmlspecialchars(Url::to($module['url']), ENT_QUOTES, 'UTF-8') ?>"
                                   <?= $cleActive === $module['cle'] ? 'aria-current="page"' : '' ?>>
                                    <?= $icone($module['icone']) ?>
                                    <span class="nav-link-title"><?= htmlspecialchars($module['libelle'], ENT_QUOTES, 'UTF-8') ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- 3. Actions et profil, à droite -->
                <div class="col-auto d-flex align-items-center ms-auto">
                    <?php if ($user !== null): ?>
                        <span class="badge bg-blue-lt d-none d-xl-inline-flex align-items-center me-3">
                            <?= $icone('shield-halved', 'me-1') ?>
                            <?= htmlspecialchars(\Models\User::ROLES[(string) $user['role']] ?? $user['role'], ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link d-flex lh-1 text-reset p-0 px-2"
                               data-bs-toggle="dropdown" data-bs-auto-close="outside"
                               aria-expanded="false" aria-label="Menu utilisateur">
            <?php $avatarUrl = \Models\User::avatarUrl($user['avatar'] ?? null); ?>
            <?php $initiales  = mb_strtoupper(mb_substr((string) $user['nom'], 0, 1)); ?>
            <span class="avatar avatar-sm bg-primary-lt me-2<?= $avatarUrl !== null ? ' bg-cover' : '' ?>"
                  <?= $avatarUrl !== null ? 'style="background-image: url(\'' . htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') . '\')"' : '' ?>>
                <?= $avatarUrl === null ? $initiales : '' ?>
            </span>
                                <span class="d-none d-lg-block">
                                    <span class="d-block"><?= htmlspecialchars((string) $user['nom'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="d-block mt-1 small text-secondary"><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                                <?= $icone('chevron-down', 'ms-2 d-none d-lg-inline-block') ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <div class="dropdown-header d-lg-none">
                                    <?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <a class="dropdown-item" href="<?= htmlspecialchars(Url::to('/profil'), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= $icone('user') ?> Mon profil
                                </a>
                                <a class="dropdown-item" href="<?= htmlspecialchars(Url::to('/docs/features'), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= $icone('book') ?> Fonctionnalités
                                </a>
                                <div class="dropdown-divider"></div>
                                <form method="post" action="<?= htmlspecialchars(Url::to('/logout'), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= Core\Csrf::field() ?>
                                    <button type="submit" class="dropdown-item text-danger">
                                        <?= $icone('right-from-bracket') ?> Déconnexion
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </header>

    <div class="page-wrapper">
        <div class="page-body">
            <!-- Corps de page : pleine largeur. `container-xl` plafonnait à
                 1140 px et centrait le contenu, laissant de larges marges
                 inutiles sur grand écran. `px-4 px-lg-5` conserve une marge
                 latérale nette sur les bords ; ces utilitaires portent
                 `!important` et l'emportent donc sur le padding par défaut de
                 `.container-fluid`, sans CSS maison. -->
            <div class="container-fluid px-4 px-lg-5">
                <h1 class="page-title d-none d-print-block"><?= htmlspecialchars($pageTitre, ENT_QUOTES, 'UTF-8') ?></h1>

                <?php foreach ($flashes as $flash): ?>
                    <div class="alert alert-<?= htmlspecialchars((string) $flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible"
                         role="alert" data-flotteo-toast="<?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                    </div>
                <?php endforeach; ?>