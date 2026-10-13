<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;

/**
 * Migration à la volée pour la suppression logique (soft delete).
 *
 * Ajoute la colonne `deleted_at` et un index sur les tables :
 * - vehicules
 * - maintenances
 * - incidents
 * - vehicules_documents
 * - releves_odometre
 */
final class SoftDeleteMigration
{
    private static bool $execute = false;

    public static function run(): void
    {
        if (self::$execute) {
            return;
        }
        self::$execute = true;

        $tables = [
            'vehicules'           => 'idx_vehicules_deleted',
            'maintenances'        => 'idx_maintenances_deleted',
            'incidents'           => 'idx_incidents_deleted',
            'vehicules_documents' => 'idx_documents_deleted',
            'releves_odometre'    => 'idx_releves_deleted',
        ];

        foreach ($tables as $table => $index) {
            try {
                Database::run("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL");
            } catch (\Throwable $e) {
                // Ignore si déjà existant ou non supporté
            }

            try {
                Database::run("ALTER TABLE `$table` ADD INDEX IF NOT EXISTS `$index` (deleted_at)");
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
}
