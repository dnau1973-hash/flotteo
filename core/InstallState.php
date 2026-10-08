<?php
declare(strict_types=1);

namespace Core;

/**
 * Témoin d'installation et garde-fou de l'assistant de déploiement.
 *
 * L'application distingue trois états :
 *   - `installe`     : le fichier de verrouillage existe et la configuration est exploitable ;
 *   - `a_installer`  : aucun verrou, l'assistant doit être exécuté ;
 *   - `defaillant`   : verrou présent mais configuration absente ou illisible.
 *
 * Le verrouillage est délibérément un fichier séparé de `config/database.php` :
 * ce dernier est livré avec l'application et ne prouve donc rien. Seul
 * `install.lock`, écrit en toute fin d'installation réussie, fait foi.
 */
final class InstallState
{
    public const ETAT_INSTALLE    = 'installe';
    public const ETAT_A_INSTALLER = 'a_installer';
    public const ETAT_DEFAILLANT  = 'defaillant';

    /** Chemin du fichier de verrouillage, hors racine servie par Apache. */
    public static function lockPath(): string
    {
        return dirname(__DIR__) . '/storage/install.lock';
    }

    public static function configPath(): string
    {
        return dirname(__DIR__) . '/config/database.php';
    }

    /**
     * Emplacement du jeton GitHub.
     *
     * Chemin unique, également connu de `Services\GithubClient` : l'assistant
     * écrit le fichier, le module de mise à jour le lit, et les deux ne
     * peuvent pas diverger sur le nom du fichier ni sur sa clé.
     */
    public static function secretsPath(): string
    {
        return dirname(__DIR__) . '/config/secrets.php';
    }

    /** Le verrou d'installation est-il présent et lisible ? */
    public static function verrouille(): bool
    {
        return is_file(self::lockPath());
    }

    /** Métadonnées du verrou : version et date d'installation. */
    public static function metadonnees(): array
    {
        if (!self::verrouille()) {
            return [];
        }
        $brut = @file_get_contents(self::lockPath());
        if ($brut === false) {
            return [];
        }
        $donnees = json_decode($brut, true);

        return is_array($donnees) ? $donnees : [];
    }

    /**
     * État global de l'installation.
     *
     * @return array{etat: string, motif: string, metadonnees: array}
     */
    public static function status(): array
    {
        if (!self::verrouille()) {
            return [
                'etat'      => self::ETAT_A_INSTALLER,
                'motif'     => 'Aucune installation détectée.',
                'metadonnees' => [],
            ];
        }

        $config = self::lireConfig();
        if ($config === null) {
            return [
                'etat'      => self::ETAT_DEFAILLANT,
                'motif'     => 'Installation verrouillée, mais config/database.php est absent ou invalide.',
                'metadonnees' => self::metadonnees(),
            ];
        }

        return [
            'etat'      => self::ETAT_INSTALLE,
            'motif'     => 'Installation valide.',
            'metadonnees' => self::metadonnees(),
        ];
    }

    public static function estInstalle(): bool
    {
        return self::status()['etat'] === self::ETAT_INSTALLE;
    }

    /**
     * Lit `config/database.php` sans l'exécuter.
     * Retourne null si le fichier est absent, illisible ou syntaxiquement invalide.
     *
     * @return array<string, mixed>|null
     */
    public static function lireConfig(): ?array
    {
        $chemin = self::configPath();
        if (!is_file($chemin) || !is_readable($chemin)) {
            return null;
        }

        try {
            $contenu = @file_get_contents($chemin);
            if ($contenu === false) {
                return null;
            }
            // Depuis PHP 7, une erreur de syntaxe dans un fichier inclus lève une
            // ParseError (sous-classe de Error) : le try/catch la neutralise.
            $config = require $chemin;
        } catch (\Throwable $e) {
            Logger::error('Lecture de la configuration BDD impossible', $e);
            return null;
        }

        return is_array($config) && isset($config['database'], $config['host']) ? $config : null;
    }

    /**
     * Écrit le verrou d'installation.
     * Le fichier est créé hors racine web, en écriture exclusive.
     */
    public static function verrouiller(string $version): bool
    {
        $chemin  = self::lockPath();
        $dossier = dirname($chemin);
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            Logger::error("Dossier de verrouillage inaccessible : $dossier");
            return false;
        }

        $contenu = json_encode([
            'application'  => 'Flotteo',
            'version'      => $version,
            'installee_le' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        return self::ecrireAtomique($chemin, $contenu, 0640);
    }

    /** Supprime le verrou (opération de maintenance, hors assistant web). */
    public static function deverrouiller(): bool
    {
        return !is_file(self::lockPath()) || @unlink(self::lockPath());
    }

    /**
     * Écriture atomique : fichier temporaire puis renommage, afin qu'un
     * plantage en cours de route ne laisse jamais un fichier à moitié écrit.
     */
    public static function ecrireAtomique(string $destination, string $contenu, int $mode): bool
    {
        $dossier = dirname($destination);
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            Logger::error("Dossier d'écriture inaccessible : $dossier");
            return false;
        }

        $temporaire = $destination . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $ecrit = @file_put_contents($temporaire, $contenu, LOCK_EX);

        if ($ecrit === false || $ecrit !== strlen($contenu)) {
            @unlink($temporaire);
            Logger::error("Échec d'écriture du fichier temporaire pour $destination");
            return false;
        }

        @chmod($temporaire, $mode);
        if (!@rename($temporaire, $destination)) {
            @unlink($temporaire);
            Logger::error("Renommage atomique impossible vers $destination");
            return false;
        }
        @chmod($destination, $mode);

        return true;
    }
}