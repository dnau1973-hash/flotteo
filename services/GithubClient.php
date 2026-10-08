<?php
declare(strict_types=1);

namespace Services;

use Core\Logger;

/**
 * Consultation de l'API GitHub pour rechercher les mises à jour de l'application.
 *
 * Aucune bibliothèque tierce : l'appel est fait par cURL, disponible dans la
 * configuration PHP de Flotteo. Le dépôt est saisi par un administrateur dans
 * les paramètres, il est donc traité comme une entrée hostile :
 *
 *  - la forme `owner/repo` est validée avant tout appel, ce qui écarte une URL
 *    complète (`https://…`) tout autant qu'un chemin ambigu (`../`) ;
 *  - chaque segment est ré-encodé séparément, l'API ne reçoit donc jamais une
 *    adresse fabriquée à partir d'une saisie ;
 *  - `CURLOPT_PROTOCOLS` est verrouillé sur HTTPS et les redirections ne sont
 *    pas suivies : une réponse 30x ne peut pas faire sortir l'appel vers un autre
 *    hôte que `api.github.com`.
 *
 * Aucune méthode ne lève d'exception : un échec réseau, un dépôt absent ou une
 * limite de débit sont tous rendus sous la forme d'un message compréhensible, car
 * ce contrôle est une information d'ergonomie et ne doit jamais interrompre la
 * page des paramètres.
 */
final class GithubClient
{
    /** Hôte unique de l'API : jamais reconstruit à partir d'une saisie. */
    private const HOTE = 'api.github.com';

    /** Délai maximal d'une requête, en secondes. */
    private const DELAI_CONNEXION = 5;
    private const DELAI_REPONSE = 8;

    /**
     * Plafond de la réponse lue. Une page de version tient largement en dessous ;
     * la limite évite qu'une réponse anormalement volumineuse soit chargée en
     * mémoire alors qu'elle n'est pas exploitable.
     */
    private const TAILLE_MAX = 262144;

    private string $message = '';

    /** Fichier de secrets, hors racine servie par Apache. */
    private const FICHIER_SECRETS = __DIR__ . '/../config/secrets.php';

    /** Jeton lu une seule fois par requête. */
    private static ?string $jeton = null;

    /** Statut HTTP de la dernière réponse, 0 si aucune requête n'a abouti. */
    private int $statut = 0;

    /**
     * Valide la forme d'un dépôt GitHub.
     *
     * @return string|null Le dépôt normalisé, ou null s'il est irrecevable.
     */
    public static function validerDepot(string $depot): ?string
    {
        $depot = trim(str_replace('https://github.com/', '', $depot));
        $depot = rtrim(trim($depot), '/');

        // Un compte et un dépôt : ni séparateur de chemin, ni séparateur de
        // requête, ni espace. Le tiret bas et le point sont admis par GitHub.
        if (preg_match('#^[A-Za-z0-9._-]{1,100}/[A-Za-z0-9._-]{1,100}$#', $depot) !== 1) {
            return null;
        }

        return $depot;
    }

    /**
     * Dernière version publiée d'un dépôt.
     *
     * L'API « latest » ignore les versions préliminaires et les brouillons,
     * ce qui convient à un contrôle de mise à jour : proposer une bêta comme
     * mise à jour normale conduirait à installer du code non stabilisé.
     *
     * Elle répond cependant 404 pour un dépôt qui **versionne par étiquettes
     * sans créer de version** — usage très répandu. Le repli sur `/tags` couvre
     * ce cas ; `source` dit alors à l'écran d'où vient le numéro.
     *
     * @return array{succes: bool, message: string, depot: string, version: string,
     *               etiquette: string, url: string, publie_le: string, source: string}
     */
    public function derniereVersion(string $depot): array
    {
        $vide = [
            'succes'    => false,
            'message'   => '',
            'depot'     => '',
            'version'   => '',
            'etiquette' => '',
            'url'       => '',
            'publie_le' => '',
            'source'    => '',
        ];

        $saisi = trim($depot);

        $depot = self::validerDepot($saisi);
        if ($depot === null) {
            $this->message = 'Le dépôt est vide ou mal formé. Indiquez-le sous la forme « compte/dépôt », par exemple un compte et un nom de dépôt séparés par une barre oblique.';

            return array_merge($vide, ['depot' => $saisi, 'message' => $this->message]);
        }

        [$compte, $nom] = explode('/', $depot, 2);
        $racine = 'https://' . self::HOTE . '/repos/' . rawurlencode($compte) . '/' . rawurlencode($nom);

        $brut = $this->appeler($racine . '/releases/latest');
        if ($brut === null) {
            // L'API répond 404 aussi bien pour un dépôt inexistant que pour un
            // dépôt qui ne publie aucune version : dans les deux cas elle ne
            // distingue rien. Une requête sur le dépôt lui-même tranche, et
            // n'est faite que sur ce chemin d'erreur.
            if ($this->statut !== 404 || $this->appeler($racine) === null) {
                return array_merge($vide, ['depot' => $depot, 'message' => $this->message]);
            }

            // Le dépôt existe mais ne publie aucune version : on cherche alors
            // dans ses étiquettes.
            $repli = $this->parEtiquettes($racine, $compte, $nom);

            if (is_array($repli)) {
                return $repli;
            }

            if ($repli === false) {
                // L'appel sur les étiquettes a lui-même échoué : le message
                // d'origine (coupure réseau, limite de débit) reste le bon.
                return array_merge($vide, ['depot' => $depot, 'message' => $this->message]);
            }

            // Le dépôt existe : il ne reste qu'aucune étiquette stable n'y
            // figure, ce qui couvre aussi le dépôt dont toutes les étiquettes
            // sont des préversions.
            $this->message = 'Ce dépôt ne contient ni version publiée ni étiquette de version utilisable — les préversions étant écartées. Indiquez le dépôt qui porte les publications de Flotteo.';

            return array_merge($vide, ['depot' => $depot, 'message' => $this->message]);
        }

        $payload = json_decode($brut, true);
        if (!is_array($payload)) {
            $this->message = 'La réponse de GitHub n\'a pas pu être interpretée.';

            return array_merge($vide, ['depot' => $depot, 'message' => $this->message]);
        }

        // Une version sans étiquette ne permet pas de comparer quoi que ce soit.
        $etiquette = trim((string) ($payload['tag_name'] ?? ''));
        if ($etiquette === '') {
            $this->message = 'La dernière version de ce dépôt ne porte pas d\'étiquette exploitable.';

            return array_merge($vide, ['depot' => $depot, 'message' => $this->message]);
        }

        $publie = (string) ($payload['published_at'] ?? '');
        if ($publie !== '') {
            $horodatage = strtotime($publie);
            $publie = $horodatage !== false ? date('Y-m-d H:i', $horodatage) : '';
        }

        $urlVersion = (string) ($payload['html_url'] ?? '');
        if ($urlVersion === '' || !$this->urlDeConfiance($urlVersion)) {
            // L'API renvoie normalement l'URL du dépôt demandé ; si elle en
            // désigne un autre, elle n'est pas transmise à l'écran.
            $urlVersion = 'https://github.com/' . $compte . '/' . $nom . '/releases/tag/' . rawurlencode($etiquette);
        }

        return [
            'succes'    => true,
            'message'   => 'Version publiée la plus récente relevée sur GitHub.',
            'depot'     => $depot,
            'version'   => self::versionNormalisee($etiquette),
            'etiquette' => $etiquette,
            'url'       => $urlVersion,
            'publie_le' => $publie,
            'source'    => 'version',
        ];
    }

    /**
     * Dernière version d'un dépôt repérée par ses étiquettes.
     *
     * GitHub renvoie les étiquettes dans un ordre qui suit la création des
     * références, pas l'ordre des numéros : un correctif retroporté (`v1.9.3`
     * publié après `v2.1.0`) remonterait donc en tête. Le plus haut numéro est
     * retenu à la place, sur les premières étiquettes seulement, pour ne pas
     * parcourir un dépôt au millier de versions.
     *
     * @return array<string, string|bool>|false|null Le relevé si une étiquette
     *               exploitable a été trouvée, `false` si l'appel lui-même a
     *               échoué, `null` si le dépôt n'a aucune étiquette utilisable.
     */
    private function parEtiquettes(string $racine, string $compte, string $nom): array|false|null
    {
        $brut = $this->appeler($racine . '/tags?per_page=30');
        if ($brut === null) {
            return false;
        }

        $liste = json_decode($brut, true);
        if (!is_array($liste)) {
            $this->message = 'La liste des étiquettes n\'a pas pu être interprétée.';

            return false;
        }

        $meilleure = '';
        foreach ($liste as $entree) {
            $etiquette = trim((string) ($entree['name'] ?? ''));

            // Une étiquette sans numéro de version (`latest`, `nightly`) est
            // ignorée : elle ne désigne aucune version comparable. Une
            // préversion (`v7.3-rc6`) l'est aussi : `versionNormalisee()`
            // la ramènerait à `7.3` et ferait annoncer comme disponible une
            // version qui n'est pas encore stabilisée. Le chemin des versions
            // publiées écarte déjà les préversions, celui-ci doit le faire aussi.
            $version = self::versionNormalisee($etiquette);
            if ($version === '' || self::estPreversion($etiquette)) {
                continue;
            }

            if ($meilleure === '' || version_compare($version, $meilleure, '>')) {
                $meilleure = $version;
            }
        }

        if ($meilleure === '') {
            return null;
        }

        $etiquette = '';
        foreach ($liste as $entree) {
            if (self::versionNormalisee((string) ($entree['name'] ?? '')) === $meilleure) {
                $etiquette = trim((string) $entree['name']);
                break;
            }
        }

        $this->message = '';

        return [
            'succes'    => true,
            'message'   => 'Ce dépôt ne publie aucune version : la dernière étiquette a servi de référence.',
            'depot'     => $compte . '/' . $nom,
            'version'   => $meilleure,
            'etiquette' => $etiquette,
            'url'       => 'https://github.com/' . $compte . '/' . $nom . '/releases/tag/' . rawurlencode($etiquette),
            'publie_le' => '',
            'source'    => 'etiquette',
        ];
    }

    /**
     * Compare la version publiée à celle de l'application installée.
     *
     * @return array{etat: string, message: string} `a_jour`, `disponible` ou `indetermine`
     */
    public static function comparer(string $versionInstallee, array $releve): array
    {
        if (($releve['succes'] ?? false) !== true) {
            return ['etat' => 'indetermine', 'message' => ''];
        }

        $publiee = (string) ($releve['version'] ?? '');
        $installee = self::versionNormalisee($versionInstallee);

        if ($publiee === '' || $installee === '') {
            $etiquette = (string) ($releve['etiquette'] ?? '');
            $raison = $publiee === ''
                ? 'l\'étiquette « ' . ($etiquette !== '' ? $etiquette : 'inconnue') . ' » ne contient pas de numéro de version'
                : 'la version installée n\'est pas numérotée';

            return [
                'etat'    => 'indetermine',
                'message' => 'Comparaison impossible : ' . $raison . '. Le dépôt est peut-être publié sous une convention d\'étiquettes inhabituelle.',
            ];
        }

        if (version_compare($publiee, $installee, '>')) {
            return [
                'etat'    => 'disponible',
                'message' => 'Une version ' . $publiee . ' est publiée, alors que ' . $installee . ' est installée.',
            ];
        }

        return [
            'etat'    => 'a_jour',
            'message' => 'La version installée est à jour.',
        ];
    }

    /**
     * Ramène une étiquette à une version comparable.
     *
     * `v1.2.3` et `1.2.3` désignent la même version ; `version_compare()`
     * refuserait la première forme. Les préfixes d'énoncé (`release-`) et les
     * suffixes de métadonnées (`-rc1`, `+build`) sont retirés.
     */
    public static function versionNormalisee(string $version): string
    {
        $version = ltrim(trim($version), '=');
        $version = (string) preg_replace('/^(?:release[-_]?|rel[-_]?)?v?(\d[\d.]*)/i', '$1', $version);
        $version = (string) preg_replace('/[-+].*$/', '', $version);
        $version = rtrim(trim($version), '.');

        // Une étiquette sans partie numérique (`nightly`, `latest`) ne désigne
        // pas une version : la laisser passer produirait une comparaison
        // alphabétique avec `version_compare()`, dont le résultat n'a aucun sens.
        if (preg_match('/^\d+(\.\d+)*$/', $version) !== 1) {
            return '';
        }

        return $version;
    }

    /**
     * Repère une étiquette de préversion.
     *
     * `v7.3-rc6` et `v7.3-beta1` désignent des versions de travail : les
     * proposer comme mise à jour conduirait à installer du code non
     * stabilisé, ce que le chemin des versions publiées interdit par
     * construction.
     */
    private static function estPreversion(string $etiquette): bool
    {
        return preg_match('/(?:^|[-_+.])(?:alpha|beta|rc|pre|preview|dev|next|canary|snapshot|nightly|experimental)(?:[-_+.]?\d*)$/i', $etiquette) === 1;
    }

    /** Dernier message d'échec, à exposer dans les journaux. */
    public function erreur(): string
    {
        return $this->message;
    }

    /**
     * Jeton GitHub configuré, chaîne vide en accès anonyme.
     *
     * Deux sources, l'environnement d'abord : `FLOTTEO_GITHUB_TOKEN` permet à
     * l'outillage de déploiement d'injecter le secret sans fichier à maintenir.
     * À défaut, `config/secrets.php` est lu — hors racine servie, donc jamais
     * exposé en HTTP, et absent des archives de sauvegarde qui n'embarquent que
     * la base.
     *
     * Le jeton n'est ni journalisé, ni renvoyé à l'écran : seule sa présence est
     * exposée, via `jetonConfigure()`.
     */
    public static function jeton(): string
    {
        if (self::$jeton !== null) {
            return self::$jeton;
        }

        $jeton = trim((string) (getenv('FLOTTEO_GITHUB_TOKEN') ?: ''));

        if ($jeton === '' && is_file(self::FICHIER_SECRETS)) {
            try {
                $secrets = require self::FICHIER_SECRETS;
                if (is_array($secrets)) {
                    $jeton = trim((string) ($secrets['github_token'] ?? ''));
                }
            } catch (\Throwable $e) {
                // Un fichier de secret illisible ne doit pas empêcher le contrôle
                // en accès anonyme : l'appel reste possible sur un dépôt public.
                Logger::error('config/secrets.php illisible, appel GitHub anonyme', $e);
                $jeton = '';
            }
        }

        return self::$jeton = $jeton;
    }

    /** Vrai si un jeton est disponible. Ne révèle jamais sa valeur. */
    public static function jetonConfigure(): bool
    {
        return self::jeton() !== '';
    }

    /** Quota horaire de l'API, très supérieur lorsqu'un jeton est employé. */
    public static function quotaHoraire(): int
    {
        return self::jetonConfigure() ? 5000 : 60;
    }

    /**
     * Exécute la requête et renvoie le corps de la réponse, ou null.
     *
     * Les statuts sont traduits en message : l'administrateur doit savoir
     * distinguer un dépôt mal saisi (404), une limite de débit atteinte (403) et
     * une coupure réseau, qui ne se corrigent pas de la même façon.
     */
    private function appeler(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            $this->message = 'L\'extension cURL n\'est pas disponible sur ce serveur : la recherche de mise à jour est impossible.';

            return null;
        }

        $curl = curl_init();
        if ($curl === false) {
            $this->message = 'Initialisation de la requête GitHub impossible.';

            return null;
        }

        $entetes = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        // Le jeton n'est lu que s'il existe. En son absence l'appel reste
        // anonyme, ce qui suffit à lire les versions d'un dépôt public : le
        // contrôle ne dépend donc jamais de la présence d'un secret.
        $jeton = self::jeton();
        if ($jeton !== '') {
            $entetes[] = 'Authorization: Bearer ' . $jeton;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => self::DELAI_CONNEXION,
            CURLOPT_TIMEOUT        => self::DELAI_REPONSE,
            CURLOPT_USERAGENT      => 'Flotteo',
            CURLOPT_HTTPHEADER     => $entetes,
        ]);

        $corps = curl_exec($curl);
        $erreur = curl_error($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $this->statut = $code;

        if ($corps === false || $erreur !== '') {
            $this->message = 'GitHub n\'a pas répondu (' . ($erreur !== '' ? $erreur : 'code ' . $code) . '). Vérifiez la connexion réseau du serveur.';
            Logger::error('Verification de mise a jour impossible : ' . $this->message);

            return null;
        }

        if (strlen((string) $corps) > self::TAILLE_MAX) {
            $this->message = 'La réponse de GitHub dépasse la taille attendue.';

            return null;
        }

        if ($code === 200) {
            return (string) $corps;
        }

        if ($code === 401) {
            // 401 signifie que le jeton a été transmis mais refusé : expired,
            // révoqué, ou sans le périmètre de lecture demandé.
            $this->message = 'Le jeton GitHub a été refusé. Vérifiez qu\'il est valide et qu\'il dispose du droit de lecture sur le dépôt.';
            Logger::error('Jeton GitHub refuse par l\'API (code 401)');

            return null;
        }

        if ($code === 404) {
            $this->message = 'Dépôt introuvable sur GitHub. Vérifiez le nom du compte et du dépôt, et que le dépôt est public.';

            return null;
        }

        if ($code === 403 || $code === 429) {
            $this->message = 'Limite de débit de l\'API GitHub atteinte. '
                . self::quotaHoraire()
                . ' requêtes par heure sont autorisées dans votre cas ; réessayez plus tard.';
            Logger::error('Limite de debit GitHub atteinte (code ' . $code . ')');

            return null;
        }

        $this->message = 'GitHub a renvoyé un statut inattendu (' . $code . ').';
        Logger::error('Statut inattendu de l\'API GitHub : ' . $code);

        return null;
    }

    /**
     * Vrai si l'URL renvoie bien vers la page GitHub du dépôt interrogé.
     *
     * L'API peut décrire un dépôt redirigé vers un autre compte : seule une URL
     * en `https` pointant sur github.com est transmise à l'écran, pour qu'un
     * lien de version ne puisse pas mener ailleurs que chez GitHub.
     */
    private function urlDeConfiance(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        $hote = strtolower((string) ($parts['host'] ?? ''));

        return $hote === 'github.com' || str_ends_with($hote, '.github.com');
    }
}