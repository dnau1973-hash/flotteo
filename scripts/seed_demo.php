<?php
declare(strict_types=1);

/**
 * Générateur du jeu de données de démonstration Flotteo.
 *
 * Source unique de vérité : ce script produit à la fois l'injection en base et
 * le fichier SQL statique `sql/demodata.sql` (`--sql`), afin que les deux
 * chemins ne puissent pas diverger.
 *
 * Modes :
 *   php scripts/seed_demo.php             injecte le jeu de données
 *   php scripts/seed_demo.php --verifier  contrôle la cohérence sans base
 *   php scripts/seed_demo.php --sql       régénère sql/demodata.sql
 *
 * Les identifiants de connexion des fixtures sont volontairement connus ; ils ne
 * doivent jamais être déployés en production. Le hachage est calculé à
 * l'exécution (`password_hash`) : aucun condensat n'est recopié dans le dépôt.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ce script est réservé à la ligne de commande.');
}

define('FLOTTEO_ROOT', dirname(__DIR__));

// Chargement explicite : le générateur s'exécute en CLI, hors du front controller.
require FLOTTEO_ROOT . '/scripts/seed/Data.php';
require FLOTTEO_ROOT . '/scripts/seed/Options.php';
require FLOTTEO_ROOT . '/scripts/seed/Calendrier.php';
require FLOTTEO_ROOT . '/scripts/seed/MdpDemo.php';
require FLOTTEO_ROOT . '/scripts/seed/Message.php';
require FLOTTEO_ROOT . '/scripts/seed/Sql.php';
require FLOTTEO_ROOT . '/scripts/seed/Injecteur.php';

use Seed\Data as Jeu;

$options = Seed\Options::analyser($argv);

/** Mot de passe en clair des trois comptes de démonstration. */
const MDP_DEMO = 'Password123!';

// --- Vérification de cohérence (aucune base requise) -----------------------
$erreurs = Jeu::verifier();

if ($erreurs !== []) {
    fwrite(STDERR, "Jeu de données incohérent :\n");
    foreach ($erreurs as $erreur) {
        fwrite(STDERR, '  - ' . $erreur . PHP_EOL);
    }
    exit(1);
}

if ($options->verification) {
    fwrite(STDOUT, sprintf(
        "Jeu de données cohérent : %d utilisateurs, %d véhicules, %d interventions, %d incidents, %d fichiers.\n",
        count(Jeu::utilisateurs()),
        count(Jeu::vehicules()),
        count(Jeu::maintenances()),
        count(Jeu::incidents()),
        count(Jeu::fichiers())
    ));
    exit(0);
}

if ($options->sql !== null) {
    // Une incohérence interne du générateur doit rester un message lisible,
    // jamais une trace PHP : ce script est destiné à être lancé en production.
    try {
        $contenu = Seed\Sql::generer();
    } catch (\LogicException $e) {
        fwrite(STDERR, 'Génération impossible : ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    if (file_put_contents($options->sql, $contenu) === false) {
        fwrite(STDERR, 'Écriture impossible : ' . $options->sql . PHP_EOL);
        exit(1);
    }
    fwrite(STDOUT, 'Généré : ' . $options->sql . ' (' . number_format(strlen($contenu)) . " octets)\n");
    exit(0);
}

// --- Injection en base ----------------------------------------------------
exit(Seed\Injecteur::executer($options));