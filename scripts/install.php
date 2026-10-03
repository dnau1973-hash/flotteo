<?php
declare(strict_types=1);

/**
 * Installation en ligne de commande.
 *
 * Delegue a Services\Installer : la logique est strictement identique a celle
 * de l'assistant web, y compris la protection contre l'ecrasement d'une base
 * non vide. Un mot de passe en dur serait une porte ouverte ; le mot de passe
 * administrateur est donc exige en variable d'environnement.
 *
 * Usage :
 *   FLOTTEO_ADMIN_EMAIL=admin@flotteo.local \
 *   FLOTTEO_ADMIN_PASSWORD='...' \
 *   php scripts/install.php
 *
 * Les identifiants proviennent de config/database.php, modifiable au besoin.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ce script est réservé à la ligne de commande.');
}

define('FLOTTEO_ROOT', dirname(__DIR__));

require FLOTTEO_ROOT . '/core/InstallState.php';
require FLOTTEO_ROOT . '/services/Installer.php';

use Core\InstallState;
use Services\Installer;

$sortie = static function (string $message): void {
    fwrite(STDOUT, $message . PHP_EOL);
};

$erreur = static function (string $message): void {
    fwrite(STDERR, $message . PHP_EOL);
};

$conf = require FLOTTEO_ROOT . '/config/database.php';

$base = [
    'host'     => (string) $conf['host'],
    'port'     => (int) $conf['port'],
    'database' => (string) $conf['database'],
    'username' => (string) $conf['username'],
    'password' => (string) $conf['password'],
];

$email = (string) (getenv('FLOTTEO_ADMIN_EMAIL') ?: '');
$mdp   = (string) (getenv('FLOTTEO_ADMIN_PASSWORD') ?: '');

if ($email === '' || $mdp === '') {
    $erreur('Variables FLOTTEO_ADMIN_EMAIL et FLOTTEO_ADMIN_PASSWORD obligatoires.');
    $erreur('Exemple : FLOTTEO_ADMIN_EMAIL=admin@flotteo.local FLOTTEO_ADMIN_PASSWORD=\'\' php scripts/install.php');
    exit(2);
}

$etat = InstallState::status();
if ($etat['etat'] === InstallState::ETAT_INSTALLE) {
    $erreur('Flotteo est déjà installé (témoin storage/install.lock présent). Rien n\'a été modifié.');
    exit(3);
}
if ($etat['etat'] === InstallState::ETAT_DEFAILLANT) {
    $erreur('Verrou d\'installation présent mais configuration illisible : ' . $etat['motif']);
    exit(3);
}

$sortie('Test de connexion…');
$test = Installer::tester($base);
if (!$test['succes']) {
    $erreur('Connexion impossible : ' . $test['message']);
    exit(1);
}
$sortie('  ' . $test['message']);

$verif = Installer::verifierBase($base);
$sortie('  ' . $verif['message']);

$resultat = Installer::executer($base, [
    'nom'        => 'Administrateur',
    'email'      => $email,
    'motdepasse' => $mdp,
]);

if (!$resultat['succes']) {
    $erreur('Échec de l\'installation : ' . $resultat['message']);
    foreach ($resultat['etapes'] as $etape) {
        $erreur('  · ' . $etape);
    }
    exit(1);
}

$sortie('Installation terminée. Connectez-vous sur /login avec ' . $email . '.');
exit(0);
