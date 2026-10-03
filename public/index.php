<?php
declare(strict_types=1);

/**
 * Point d'entrée unique de l'application Flotteo (front controller).
 */

use Core\Auth;
use Core\Flash;
use Core\InstallState;
use Core\Request;
use Core\Response;
use Core\Router;
use Core\Url;

define('FLOTTEO_ROOT', dirname(__DIR__));
define('FLOTTEO_START', microtime(true));

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// --- Autoloader PSR-4 minimaliste (Flotteo\ sans framework) -----------------
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Flotteo\\')) {
        return;
    }
    $relatif = str_replace('\\', '/', substr($class, strlen('Flotteo\\')));
    $fichier = FLOTTEO_ROOT . '/' . $relatif . '.php';
    if (is_file($fichier)) {
        require $fichier;
    }
});

/**
 * Autoloader applicatif : l'arborescence du cahier des charges impose des
 * répertoires en minuscules (core/, controllers/, models/) tandis que PHP
 * exige des noms de classes capitalisés. Une table de correspondance
 * explicite évite toute surprise de casse sur les systèmes Linux.
 */
const FLOTTEO_CLASS_MAP = [
    'Core\\'            => 'core/',
    'Controllers\\'     => 'controllers/',
    'Models\\'          => 'models/',
    'Services\\'        => 'services/',
    'Views\\'           => 'views/',
];

spl_autoload_register(static function (string $class): void {
    foreach (FLOTTEO_CLASS_MAP as $prefixe => $dossier) {
        if (str_starts_with($class, $prefixe)) {
            $fichier = FLOTTEO_ROOT . '/' . $dossier . str_replace('\\', '/', substr($class, strlen($prefixe))) . '.php';
            if (is_file($fichier)) {
                require $fichier;
                return;
            }
        }
    }
});

require FLOTTEO_ROOT . '/core/Database.php';
require FLOTTEO_ROOT . '/core/Logger.php';
require FLOTTEO_ROOT . '/core/Request.php';
require FLOTTEO_ROOT . '/core/Response.php';
require FLOTTEO_ROOT . '/core/View.php';
require FLOTTEO_ROOT . '/core/Auth.php';
require FLOTTEO_ROOT . '/core/Csrf.php';
require FLOTTEO_ROOT . '/core/Flash.php';
require FLOTTEO_ROOT . '/core/Upload.php';
require FLOTTEO_ROOT . '/core/Mailer.php';
require FLOTTEO_ROOT . '/core/Url.php';
require FLOTTEO_ROOT . '/core/InstallState.php';
require FLOTTEO_ROOT . '/core/Controller.php';
require FLOTTEO_ROOT . '/core/Router.php';

Auth::start();

/**
 * --- Verrou d'installation ------------------------------------------------
 * Tant que le témoin storage/install.lock est absent, aucune route applicative
 * n'est atteignable : l'utilisateur est immediately redirigé vers l'assistant.
 * Une fois le témoin présent, l'assistant est verrouillé à son tour, ce qui
 * interdit toute réinitialisation malveillante.
 */
$cheminDemande = Router::currentPath();
$estAssistant  = $cheminDemande === '/install' || str_starts_with($cheminDemande, '/api/install/');
$etatInstall   = InstallState::status();

if (!$estAssistant && $etatInstall['etat'] === InstallState::ETAT_A_INSTALLER) {
    Response::redirect(Url::to('/install'));
}

if ($estAssistant && $etatInstall['etat'] !== InstallState::ETAT_A_INSTALLER) {
    // Seul l'état « à installer » ouvre l'assistant. Les deux états où le témoin
    // est présent le verrouillent, y compris « défaillant » : ouvrir l'assistant
    // alors qu'une base est peut-être déjà en service autoriserait un visiteur
    // non authentifié à l'écraser.
    Flash::add('warning', 'Flotteo est déjà installé : l\'assistant est désactivé.');
    Response::redirect(Url::to('/login'));
}

if (!$estAssistant && $etatInstall['etat'] === InstallState::ETAT_DEFAILLANT) {
    // Verrou présent mais configuration illisible : on n'ouvre surtout pas
    // l'assistant, qui risquerait d'écraser une base déjà en service.
    http_response_code(503);
    (new Core\View())->render('erreur/503', [
        'motif'      => $etatInstall['motif'],
        'metadonnees' => $etatInstall['metadonnees'],
    ], false);
    exit;
}

$router = new Router();
(require FLOTTEO_ROOT . '/routes.php')($router);
$router->dispatch();
