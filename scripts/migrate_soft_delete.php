<?php
declare(strict_types=1);

/**
 * Migration : ajout de la colonne `deleted_at` (suppression logique)
 * sur les tables véhicules, maintenances, incidents, vehicules_documents
 * et releves_odometre.
 *
 * Usage : php scripts/migrate_soft_delete.php
 */

define('FLOTTEO_ROOT', dirname(__DIR__));
require FLOTTEO_ROOT . '/core/Database.php';
require FLOTTEO_ROOT . '/core/Logger.php';

$pdo = Core\Database::pdo();

$tables = [
    'vehicules',
    'maintenances',
    'incidents',
    'vehicules_documents',
    'releves_odometre',
];

foreach ($tables as $t) {
    try {
        $pdo->exec("ALTER TABLE `$t` ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
        echo "OK  colonne: $t.deleted_at\n";
    } catch (Exception $e) {
        echo "ERR colonne $t: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE `$t` ADD INDEX IF NOT EXISTS idx_{$t}_deleted (deleted_at)");
        echo "OK  index:   idx_{$t}_deleted\n";
    } catch (Exception $e) {
        echo "ERR index $t: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration soft-delete terminée.\n";
