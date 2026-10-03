<?php
declare(strict_types=1);

/**
 * Table de routage applicative.
 * Convention : /module/action, toute action d'écriture est en POST.
 */

use Core\Router;
use Controllers\ApiController;
use Controllers\AuthController;
use Controllers\DashboardController;
use Controllers\DictionaryController;
use Controllers\DocsController;
use Controllers\EcheanceController;
use Controllers\IncidentController;
use Controllers\InstallController;
use Controllers\MaintenanceController;
use Controllers\ParamController;
use Controllers\UserController;
use Controllers\VehicleController;

/** Enregistre l'ensemble des routes de l'application. */
return static function (Router $router): void {

// --- Assistant d'installation (accessible uniquement si non installé) -------
$router->get('/install', InstallController::class, 'index');
$router->post('/install', InstallController::class, 'executer');
$router->post('/api/install/tester', InstallController::class, 'tester');

// --- Authentification --------------------------------------------------------
$router->get('/login', AuthController::class, 'form');
$router->post('/login', AuthController::class, 'login');
$router->post('/logout', AuthController::class, 'logout');
$router->get('/profil', AuthController::class, 'profile');
$router->post('/profil', AuthController::class, 'updateProfile');

// --- Dashboard ---------------------------------------------------------------
$router->get('/', DashboardController::class, 'index');
$router->get('/dashboard', DashboardController::class, 'index');

// --- Flotte ------------------------------------------------------------------
$router->get('/vehicules', VehicleController::class, 'index');
$router->get('/vehicules/voir', VehicleController::class, 'show');
$router->post('/vehicules/enregistrer', VehicleController::class, 'save');
$router->post('/vehicules/supprimer', VehicleController::class, 'delete');

// --- Entretien ---------------------------------------------------------------
$router->get('/entretien', MaintenanceController::class, 'index');
$router->post('/entretien/enregistrer', MaintenanceController::class, 'save');
$router->post('/entretien/supprimer', MaintenanceController::class, 'delete');

// --- Incidents ---------------------------------------------------------------
$router->get('/incidents', IncidentController::class, 'index');
$router->get('/incidents/voir', IncidentController::class, 'show');
$router->post('/incidents/enregistrer', IncidentController::class, 'save');
$router->post('/incidents/supprimer', IncidentController::class, 'delete');
$router->post('/incidents/fichier', IncidentController::class, 'upload');
$router->get('/incidents/fichier/telecharger', IncidentController::class, 'download');
$router->post('/incidents/fichier/supprimer', IncidentController::class, 'deleteFile');

// --- Échéancier des fins de contrat -----------------------------------------
$router->get('/echeances', EcheanceController::class, 'index');

// --- Administration : utilisateurs ------------------------------------------
$router->get('/admin/utilisateurs', UserController::class, 'index');
$router->post('/admin/utilisateurs/enregistrer', UserController::class, 'save');
$router->post('/admin/utilisateurs/supprimer', UserController::class, 'delete');

// --- Administration : dictionnaires -----------------------------------------
$router->get('/admin/dictionnaires', DictionaryController::class, 'index');
$router->get('/admin/dictionnaires/{type}', DictionaryController::class, 'index');
$router->post('/admin/dictionnaires/{type}/enregistrer', DictionaryController::class, 'save');
$router->post('/admin/dictionnaires/{type}/supprimer', DictionaryController::class, 'delete');

// --- Administration : paramètres système ------------------------------------
$router->get('/admin/parametres', ParamController::class, 'index');
$router->get('/admin/parametres/{section}', ParamController::class, 'index');
$router->post('/admin/parametres/{section}/enregistrer', ParamController::class, 'save');
$router->post('/admin/parametres/messagerie/tester', ParamController::class, 'testerCourriel');
$router->post('/alertes/executer', ParamController::class, 'runAlerts');

// --- Registres documentaires (docs/ est hors de la racine servie par Apache) -
// Le Markdown est converti à la demande et rendu dans le layout général : ces
// pages sont des écrans applicatifs, plus des fichiers HTML diffusés en bloc.
$router->get('/docs', DocsController::class, 'index');
$router->get('/docs/{doc}', DocsController::class, 'show');

// --- API JSON (ApexCharts) ---------------------------------------------------
$router->get('/api/dashboard/kpis', ApiController::class, 'kpis');
$router->get('/api/dashboard/echeancier', ApiController::class, 'exitSchedule');
$router->get('/api/dashboard/repartition', ApiController::class, 'fleetSplit');
$router->get('/api/dashboard/entretien', ApiController::class, 'maintenanceCosts');
$router->get('/api/dashboard/tco-modeles', ApiController::class, 'tcoByModel');
$router->get('/api/dashboard/incidents', ApiController::class, 'incidents');
$router->get('/api/vehicules/liste', ApiController::class, 'vehicleList');
};
