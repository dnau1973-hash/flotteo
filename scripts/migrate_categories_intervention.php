<?php
declare(strict_types=1);

/**
 * Migration CLI : création de la table categories_intervention
 * et assouplissement de types_intervention.categorie.
 *
 * Usage : php scripts/migrate_categories_intervention.php
 */

define('FLOTTEO_ROOT', dirname(__DIR__));
require FLOTTEO_ROOT . '/core/Database.php';
require FLOTTEO_ROOT . '/core/Logger.php';
require FLOTTEO_ROOT . '/models/Dictionary.php';

use Models\Dictionary;

try {
    echo "Exécution de la migration categories_intervention...\n";
    Dictionary::ensureCategoriesTable();
    echo "OK : table categories_intervention vérifiée/créée.\n";
    echo "OK : types_intervention.categorie assoupli en VARCHAR(80).\n";
    $nb = count(Dictionary::categoriesListe());
    echo "OK : $nb catégorie(s) disponible(s).\n";
} catch (\Throwable $e) {
    echo "ERR : " . $e->getMessage() . "\n";
    exit(1);
}

