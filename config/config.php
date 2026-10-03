<?php
declare(strict_types=1);

/**
 * Configuration générale de l'application Flotteo.
 * Les identifiants de base de données sont externalisés dans config/database.php.
 */

return [
    'app' => [
        'nom'      => 'Flotteo',
        'baseline' => 'Gestion de parc automobile',
        'version'  => '1.0.1',
        'debug'    => false,
        'devise'   => 'EUR',
    ],
    /*
     * Préfixe d'URL publique.
     * null = détection automatique à partir de SCRIPT_NAME (recommandé) :
     * l'application fonctionne à la racine du domaine comme dans un
     * sous-dossier, sans modification du code. Renseigner une chaîne
     * ('/flotteo') ne force le préfixe que si la détection est impossible.
     */
    'base_url' => null,
    'session' => [
        'nom'     => 'FLOTTEO_SID',
        'lifetime' => 7200,
    ],
    'securite' => [
        'taille_max_upload' => 8 * 1024 * 1024,
        'mime_autorises'    => [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
        ],
    ],
    'uploads' => [
        // Chemin disque. L'URL publique correspondante est produite par
        // Core\Url::upload(), qui déduit le préfixe de base comme le reste de
        // l'application : aucun préfixe n'est figé ici.
        'chemin'    => dirname(__DIR__) . '/public/uploads',
    ],
    'alertes' => [
        'paliers_jours'   => [90, 180, 270],
        'periode_max_ans' => 2,
    ],
];
