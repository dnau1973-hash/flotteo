<?php
declare(strict_types=1);

namespace Core;

/**
 * Construction des URL publiques de l'application.
 *
 * Le préfixe de base n'est jamais codé en dur : il est déduit de l'emplacement
 * réel du front controller devant Apache (`SCRIPT_NAME`). L'application
 * fonctionne ainsi indifféremment à la racine du domaine, dans un sous-dossier
 * (`/flotteo/`) ou derrière un alias, sans modification du code.
 *
 * Toutes les vues doivent passer par ce helper : c'est la seule garantie que
 * `<link>`, `<script>`, `<link rel="manifest">` et les redirections pointent
 * vers des fichiers réellement existants.
 */
final class Url
{
    private static ?string $base = null;

    /**
     * Jeton de version par chemin d'asset, mémorisé le temps de la requête.
     *
     * @var array<string, int|null>
     */
    private static array $versions = [];

    /**
     * Préfixe de base sans slash final : chaîne vide si l'application est
     * servie à la racine du domaine.
     */
    public static function base(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }

        $impose = (require dirname(__DIR__) . '/config/config.php')['base_url'];
        if (is_string($impose) && trim($impose, '/') !== '') {
            return self::$base = '/' . trim($impose, '/');
        }

        // Ligne de commande : aucun contexte web exploitable.
        if (PHP_SAPI === 'cli') {
            return self::$base = '';
        }

        return self::$base = self::detect((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    }

    /**
     * Déduit le préfixe de base du chemin du front controller.
     * Fonction pure : testable sans contexte web.
     *
     * /index.php                         -> ''            (racine du domaine)
     * /flotteo/index.php                 -> '/flotteo'
     * /apps/flotteo/public/index.php     -> '/apps/flotteo/public'
     */
    public static function detect(string $scriptName): string
    {
        $repertoire = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        return ($repertoire === '' || $repertoire === '.') ? '' : $repertoire;
    }

    /** URL absolue d'une route applicative : `to('/vehicules')`. */
    public static function to(string $chemin = '/'): string
    {
        $chemin = '/' . ltrim($chemin, '/');
        return self::base() . ($chemin === '/' ? '/' : $chemin);
    }

    /**
     * URL absolue d'une ressource statique servie depuis public/assets.
     *
     * Volontairement sans `is_file()` : la vérification d'existence est faite
     * par le serveur web, dont la réponse 404 est plus parlante qu'un lien cassé
     * silencieux côté PHP. La version d'URL, elle, n'est ajoutée que si le
     * fichier existe.
     *
     * **Version d'URL obligatoire.** `public/.htaccess` accorde aux CSS, JS, SVG
     * et polices une durée de vie de sept jours — sans quoi chaque visite
     * renégocierait avec le serveur. Sans jeton de version, un asset corrigé
     * reste donc invisible jusqu'à l'expiration de cette durée : le navigateur
     * sert sa copie et ne redemande jamais le fichier. Symptôme rencontré : le
     * navigateur ne sollicitait plus du tout `/assets/js/utilisateurs.js`, et
     * servait une version antérieure des correctifs.
     *
     * Le jeton est l'horodatage de modification du fichier. Il change donc au
     * moment exact où le contenu change, sans aucune intervention : pas de
     * version à incrémenter à la main, aucun risque d'oubli. Comme l'URL
     * change, l'entrée en cache de l'ancienne version devient inutile et le
     * navigateur redemande le fichier une seule fois.
     */
    public static function asset(string $chemin): string
    {
        $url = self::base() . '/assets/' . ltrim($chemin, '/');

        return ($version = self::versionAsset($chemin)) === null ? $url : $url . '?v=' . $version;
    }

    /**
     * Jeton de version d'un asset : horodatage du fichier, ou null s'il est
     * absent. Mémorisé pour ne pas répéter l'appel système sur une même page.
     *
     * @return int|null
     */
    private static function versionAsset(string $chemin): ?int
    {
        $chemin = ltrim($chemin, '/');
        if (array_key_exists($chemin, self::$versions)) {
            return self::$versions[$chemin];
        }

        $fichier = dirname(__DIR__) . '/public/assets/' . $chemin;
        if (!is_file($fichier)) {
            return self::$versions[$chemin] = null;
        }

        $horodatage = @filemtime($fichier);

        return self::$versions[$chemin] = ($horodatage === false ? null : $horodatage);
    }

    /**
     * URL absolue d'un fichier déposé par un utilisateur sous public/uploads.
     *
     * Les pièces jointes d'incident ne passent pas par là : elles sont privées et
     * servies en téléchargement contrôlé par `IncidentController`. Seules les
     * images affichées directement dans une page — les avatars — sont servies
     * ainsi, le dossier `public/uploads/` interdisant toute interprétation de
     * script et n'acceptant que les types d'images autorisés.
     */
    public static function upload(string $chemin): string
    {
        return self::base() . '/uploads/' . ltrim($chemin, '/');
    }

    /** URL absolue du manifeste d'application, servi à la racine du front controller. */
    public static function manifest(): string
    {
        return self::to('/app.json');
    }
}
