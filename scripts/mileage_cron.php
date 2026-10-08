<?php
declare(strict_types=1);

/**
 * Tâche CLI : vérification quotidienne et relances des relevés kilométriques en retard.
 *
 * Usage :  php scripts/mileage_cron.php
 * Exemple crontab (tous les jours à 07h00) :
 *   0 7 * * * /usr/bin/php /var/www/flotteo/scripts/mileage_cron.php
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
require FLOTTEO_ROOT . '/core/Mailer.php';
require FLOTTEO_ROOT . '/core/Url.php';
require FLOTTEO_ROOT . '/models/User.php';
require FLOTTEO_ROOT . '/models/Vehicle.php';
require FLOTTEO_ROOT . '/models/Mileage.php';
require FLOTTEO_ROOT . '/services/MileageAlertService.php';

use Services\MileageAlertService;

$sortie = static function (string $message): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
};

try {
    $sortie('Flotteo — Contrôle des relevés kilométriques.');
    $resultat = MileageAlertService::verifierEtRelancer();
    $sortie(sprintf(
        'Fin du traitement. Statut : %s | Période : %s | Relances envoyées : %d',
        $resultat['statut'] ?? 'inconnu',
        $resultat['periode'] ?? '—',
        (int) ($resultat['relances'] ?? 0)
    ));
} catch (\Throwable $e) {
    $sortie('ERREUR CRITIQUE : ' . $e->getMessage());
    exit(1);
}

