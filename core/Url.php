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
        $base = ($repertoire === '' || $repertoire === '.') ? '' : $repertoire;

        // Si le script s'exécute sous un sous-dossier se terminant par /public,
        // mais que la requête du client n'inclut pas /public (ex: /flotteo/agenda),
        // alors la base pour les routes et URLs est le dossier parent sans /public.
        if (str_ends_with($base, '/public')) {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
            if (!str_contains($uri, '/public')) {
                $base = substr($base, 0, -strlen('/public'));
            }
        }

        return $base;
    }

    /**
     * Préfixe de base pour les routes applicatives.
     * Conserve automatiquement `/index.php` si la requête courante y fait appel
     * (par exemple sur un serveur où la réécriture d'URL Apache n'est pas activée).
     */
    public static function baseRoute(): string
    {
        $base = self::base();
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($uri, '/index.php')) {
            return $base . '/index.php';
        }

        return $base;
    }

    /** URL absolue d'une route applicative : `to('/vehicules')`. */
    public static function to(string $chemin = '/'): string
    {
        $chemin = '/' . ltrim($chemin, '/');
        $base   = self::baseRoute();

        return $base . ($chemin === '/' ? (str_ends_with($base, 'index.php') ? '' : '/') : $chemin);
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
    /** Préfixe d'URL directe vers le dossier public physique (pour assets statiques et uploads). */
    public static function publicPrefix(): string
    {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        return ($dir === '' || $dir === '.') ? '' : $dir;
    }

    public static function asset(string $chemin): string
    {
        $prefix = self::publicPrefix();
        $url = ($prefix !== '' ? $prefix : self::base()) . '/assets/' . ltrim($chemin, '/');

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
        $prefix = self::publicPrefix();
        return ($prefix !== '' ? $prefix : self::base()) . '/uploads/' . ltrim($chemin, '/');
    }

    /** URL absolue du manifeste d'application, servi à la racine du front controller. */
    public static function manifest(): string
    {
        return self::to('/app.json');
    }
}
