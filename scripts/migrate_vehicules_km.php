<?php
declare(strict_types=1);

/**
 * Migration CLI : ajout des colonnes km_arrivee et km_fin sur vehicules.
 *
 * Usage : php scripts/migrate_vehicules_km.php
 */

define('FLOTTEO_ROOT', dirname(__DIR__));
require FLOTTEO_ROOT . '/core/Database.php';
require FLOTTEO_ROOT . '/core/Logger.php';
require FLOTTEO_ROOT . '/models/SoftDeleteMigration.php';
require FLOTTEO_ROOT . '/models/Vehicle.php';

use Models\Vehicle;

try {
    echo "Exécution de la migration vehicules (km_arrivee, km_fin)...\n";
    Vehicle::ensureSchema();
    echo "OK : table vehicules migrée avec succès.\n";
} catch (\Throwable $e) {
    echo "ERR : " . $e->getMessage() . "\n";
    exit(1);
}

