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
     * Volontairement sans `is_file()` : la vérification d'existence est faite
     * par le serveur web, dont la réponse 404 est plus parlante qu'un lien cassé
     * silencieux côté PHP.
     */
    public static function asset(string $chemin): string
    {
        return self::base() . '/assets/' . ltrim($chemin, '/');
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
