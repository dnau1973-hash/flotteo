<?php
declare(strict_types=1);

/**
 * Tâche CLI : sauvegarde complète de Flotteo, puis rotation.
 *
 * Usage :
 *   php bin/backup.php                produit une archive, puis purge l'ancienneté
 *   php bin/backup.php --sans-rotation produit l'archive et n'efface rien
 *   php bin/backup.php --rotation-seule  purge seulement, sans produire d'archive
 *   php bin/backup.php --sans-externalisation  n'essaie pas la copie réseau
 *   php bin/backup.php --aide         détail des options
 *
 * Exemple crontab (chaque jour à 02 h 30) :
 *   30 2 * * * /usr/bin/php /var/www/flotteo/bin/backup.php
 *
 * Codes de sortie, exploitables par le superviseur :
 *   0  archive produite et rotation appliquée (ou aucune archive à purger) ;
 *   1  échec de production, ou rotation impossible ;
 *   2  appel depuis le web, ou arguments incompris.
 *
 * Aucun secret n'est écrit dans la sortie : le partage réseau n'est désigné que
 * par son chemin et son mode d'accès.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ce script est réservé à la ligne de commande.');
}

define('FLOTTEO_ROOT', dirname(__DIR__));

require FLOTTEO_ROOT . '/core/Database.php';
require FLOTTEO_ROOT . '/core/Logger.php';
require FLOTTEO_ROOT . '/core/Request.php';
require FLOTTEO_ROOT . '/core/Response.php';
require FLOTTEO_ROOT . '/core/Auth.php';
require FLOTTEO_ROOT . '/core/Csrf.php';
require FLOTTEO_ROOT . '/core/Flash.php';
require FLOTTEO_ROOT . '/models/Backup.php';
require FLOTTEO_ROOT . '/services/BackupService.php';
require FLOTTEO_ROOT . '/services/SambaClient.php';

use Models\Backup;
use Services\BackupService;
use Services\SambaClient;

/** Trace horodatée sur la sortie standard. */
$sortie = static function (string $message): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
};

/** Erreur sur la sortie d'erreur, journalisée. */
$erreur = static function (string $message): void {
    Logger::error('Tâche de sauvegarde : ' . $message);
    fwrite(STDERR, 'Erreur : ' . $message . PHP_EOL);
};

/** Options reconnues, et leur effet. */
$options = array_slice($argv, 1);
$inconnues = array_diff($options, ['--sans-rotation', '--rotation-seule', '--sans-externalisation', '--aide']);

if (in_array('--aide', $options, true)) {
    $sortie('Usage : php bin/backup.php [options]');
    $sortie('  --sans-rotation          ne pas purger les archives anciennes');
    $sortie('  --rotation-seule         purger sans produire d\'archive');
    $sortie('  --sans-externalisation   ne pas tenter la copie vers le partage');
    $sortie('  --aide                   cette aide');
    exit(0);
}

if ($inconnues !== []) {
    $erreur('Option inconnue : ' . implode(', ', $inconnues));
    exit(2);
}

$rotationSeule     = in_array('--rotation-seule', $options, true);
$sansRotation      = in_array('--sans-rotation', $options, true);
$sansExternalisation = in_array('--sans-externalisation', $options, true);

try {
    $sortie('Flotteo — sauvegarde planifiée.');

    $reglages = Backup::reglages();
    $jours    = Backup::retentionJours();
    $sortie('Conservation : ' . $jours . ' jours.');

    $partage = (string) $reglages['sauvegarde_partage_actif'] === '1';
    if ($partage) {
        $client = new SambaClient($reglages);
        $transport = $client->transport();
        $sortie(sprintf(
            'Partage : %s (%s, transport %s).',
            $client->partage(),
            $client->estInvite() ? 'invité' : 'identifié',
            $transport ?? 'indisponible'
        ));
        if ($transport === null) {
            // L'archive est produite quand même : c'est l'externalisation qui
            // est impossible, pas la sauvegarde. Interrompre ici laisserait la
            // base sans aucune copie locale.
            $sortie('Avertissement : ' . $client->dernierMessage());
        }
    } else {
        $sortie('Partage : désactivé, archive conservée localement.');
    }

    $code = 0;

    if (!$rotationSeule) {
        $resultat = BackupService::generer(!$sansExternalisation && $partage);

        if (!$resultat['ok']) {
            $erreur($resultat['message']);
            exit(1);
        }

        $sortie($resultat['message']);
        $sortie(sprintf(
            'Contenu : %d table(s), %d fichier(s) déposé(s).',
            $resultat['tables'],
            $resultat['fichiers']
        ));

        $samba = (string) ($resultat['samba'] ?? 'non_configure');
        if ($samba === 'echec') {
            $sortie('Alerte : archive produite, copie réseau en échec — ' . $resultat['message']);
            $code = 1;
        } elseif ($samba === 'reussi') {
            $sortie('Copie réseau effectuée.');
        }
    } else {
        $sortie('Rotation seule : aucune archive produite.');
    }

    if (!$sansRotation) {
        $purge = BackupService::purger();
        $sortie($purge['message']);
        $code = $code !== 0 ? $code : ($purge['ok'] ? 0 : 1);
    } else {
        $sortie('Rotation désactivée pour cette exécution.');
    }

    $sortie('Volume total conservé : ' . SambaClient::poids(BackupService::volume()) . '.');
    $sortie('Terminé.');

    exit($code);
} catch (Throwable $e) {
    $erreur($e->getMessage());
    exit(1);
}