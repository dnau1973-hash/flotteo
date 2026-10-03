<?php
declare(strict_types=1);

/**
 * Tâche CLI : envoi du mail récapitulatif de fin de contrat.
 *
 * Usage :  php scripts/alert_cron.php
 * Exemple crontab (tous les lundis à 07h05) :
 *   5 7 * * 1 /usr/bin/php /var/www/flotteo/scripts/alert_cron.php
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
require FLOTTEO_ROOT . '/models/User.php';
require FLOTTEO_ROOT . '/models/Vehicle.php';
require FLOTTEO_ROOT . '/services/AlertService.php';

use Services\AlertService;

$sortie = static function (string $message): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
};

try {
    $sortie('Flotteo — contrôle des alertes de fin de contrat.');

    if ((int) Mailer::param('alerte_active', '1') !== 1) {
        $sortie('Alertes désactivées : aucune action.');
        exit(0);
    }

    $paliers = AlertService::paliers();
    $sortie('Paliers d\'anticipation : ' . implode(' / ', $paliers) . ' jours.');

    $resultat = AlertService::notifier();
    $sortie('Véhicules concernés : ' . $resultat['concerne']);
    $sortie($resultat['message']);

    exit($resultat['envoi'] || $resultat['concerne'] === 0 ? 0 : 1);
} catch (Throwable $e) {
    Logger::error('Échec de la tâche d\'alertes', $e);
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
