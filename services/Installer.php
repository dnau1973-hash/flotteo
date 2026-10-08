<?php
declare(strict_types=1);

namespace Services;

use Core\InstallState;
use Core\Logger;

/**
 * Exécution de l'installation : test de connexion, création de la base,
 * application du schéma, écriture de la configuration et verrouillage.
 *
 * Une seule implémentation sert l'assistant web et le script CLI
 * (`scripts/install.php`) : aucune logique d'installation n'est dupliquée.
 *
 * Sécurité : aucun identifiant ni mot de passe n'est journalisé ni retourné.
 * Les messages destinés à l'écran sont traduits en messages fonctionnels.
 */
final class Installer
{
    /** Longueur minimale du mot de passe administrateur. */
    public const MdpMin = 10;

    /**
     * Traduit une exception PDO en message utilisateur sans aucune donnée sensible.
     * Le message d'origine n'est jamais renvoyé tel quel : MySQL y inclut
     * le nom d'utilisateur et l'hôte, ce qui facilite la reconnaissance de cibles.
     */
    public static function messageErreurPdo(\PDOException $e, string $phase): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);

        return match ($code) {
            1045     => 'Accès refusé : le couple utilisateur / mot de passe est incorrect pour cette base.',
            1044     => 'Accès refusé : l\'utilisateur n\'a pas le droit d\'utiliser cette base de données.',
            1049     => 'Serveur introuvable : vérifiez l\'adresse et le port du serveur MySQL.',
            2002     => 'Connexion impossible : le serveur MySQL est injoignable sur cet hôte et ce port.',
            2003     => 'Connexion impossible : le serveur MySQL est injoignable sur cet hôte et ce port.',
            1046     => 'Base de données introuvable sur le serveur.',
            1050     => 'La table existe déjà : la base cible n\'est pas vide.',
            default  => sprintf('Échec de la phase « %s » : opération refusée par le serveur de base de données.', $phase),
        };
    }

    /**
     * Ouvre une connexion PDO avec les paramètres fournis.
     *
     * @param array{host:string,port:int,database:string,username:string,password:string} $db
     */
    public static function connecter(array $db, bool $avecBase = true): \PDO
    {
        $base = ($avecBase && $db['database'] !== '')
            ? ';dbname=' . $db['database'] . ';charset=utf8mb4'
            : ';charset=utf8mb4';

        return new \PDO(
            sprintf('mysql:host=%s;port=%d%s', $db['host'], (int) $db['port'], $base),
            $db['username'],
            $db['password'],
            [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::ATTR_TIMEOUT            => 5,
            ]
        );
    }

    /**
     * Teste la connexion sans rien modifier.
     *
     * @return array{succes: bool, message: string, detail: string}
     */
    public static function tester(array $db): array
    {
        $controle = self::validerParametres($db);
        if ($controle !== []) {
            return ['succes' => false, 'message' => implode(' ', $controle), 'detail' => 'Champs invalides'];
        }

        try {
            $pdo = self::connecter($db, false);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $pdo = null;

            return [
                'succes'  => true,
                'message' => 'Connexion au serveur réussie.',
                'detail'  => 'MySQL ' . $version,
            ];
        } catch (\PDOException $e) {
            // Journalisation volontairement limitée au code erreur : ni le nom
            // d'utilisateur, ni le mot de passe ne doivent fuiter dans les logs.
            Logger::error(sprintf(
                'Test de connexion BDD en échec (code %d)',
                (int) ($e->errorInfo[1] ?? 0)
            ));

            return [
                'succes'  => false,
                'message' => self::messageErreurPdo($e, 'test de connexion'),
                'detail'  => '',
            ];
        }
    }

    /**
     * Vérifie que la base existe et qu'elle est vide.
     * Un schéma s'appliquant par DROP, on refuse systématiquement d'écraser
     * une base contenant déjà des données.
     *
     * @return array{succes: bool, message: string, tables: string[]}
     */
    public static function verifierBase(array $db): array
    {
        try {
            $pdo = self::connecter($db, false);
            $prepare = $pdo->prepare(
                'SELECT table_name FROM information_schema.tables WHERE table_schema = :d'
            );
            $prepare->execute(['d' => $db['database']]);
            $tables = array_map('strval', $prepare->fetchAll(\PDO::FETCH_COLUMN));

            if ($tables !== []) {
                return [
                    'succes' => false,
                    'message' => sprintf(
                        'La base « %s » contient déjà %d table(s). Installation refusée : '
                        . 'les tables existantes ne seront jamais écrasées. Videz la base ou choisissez un autre nom.',
                        $db['database'],
                        count($tables)
                    ),
                    'tables'  => $tables,
                ];
            }

            return [
                'succes'  => true,
                'message' => sprintf('La base « %s » existe et est vide.', $db['database']),
                'tables'  => [],
            ];
        } catch (\PDOException $e) {
            Logger::error(sprintf('Verification de la base en echec (code %d)', (int) ($e->errorInfo[1] ?? 0)));

            return [
                'succes'  => false,
                'message' => self::messageErreurPdo($e, 'vérification de la base'),
                'tables'  => [],
            ];
        }
    }

    /**
     * Exécution complète de l'installation.
     *
     * @param array{host:string,port:int,database:string,username:string,password:string} $db
     * @param array{nom:string,email:string,motdepasse:string} $admin
     *
     * @return array{succes: bool, message: string, etapes: string[]}
     */
    public static function executer(array $db, array $admin): array
    {
        $etapes = [];
        $controle = array_merge(self::validerParametres($db), self::validerAdmin($admin));
        if ($controle !== []) {
            return ['succes' => false, 'message' => implode(' ', $controle), 'etapes' => $etapes];
        }

        $pdo = null;
        try {
            // 1. Création de la base si nécessaire.
            $pdo = self::connecter($db, false);
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '', $db['database'])
            ));
            $etapes[] = 'Base de données créée.';

            $pdo->exec(sprintf('USE `%s`', str_replace('`', '', $db['database'])));
            $pdo = null;

            // 2. Garde-fou : jamais d'écrasement d'une base existante.
            $pdo = self::connecter($db, true);
            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
            if ($tables !== []) {
                return [
                    'succes' => false,
                    'message' => 'La base contient déjà des tables : installation interrompue, aucune donnée n\'a été modifiée.',
                    'etapes'  => $etapes,
                ];
            }

            // 3. Application du schéma complet, puis contrôle : chaque table
            //    décrite dans schema.sql doit exister, sinon l'installation
            //    est déclarée en échec (aucune migration ne viendra compléter).
            self::appliquerSchema($pdo);
            $attendues = self::tablesAttendues();
            $creees    = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN));
            $manquantes = array_values(array_diff($attendues, $creees));
            if ($manquantes !== []) {
                return [
                    'succes'  => false,
                    'message' => 'Schéma incomplet : table(s) non créée(s) : ' . implode(', ', $manquantes) . '.',
                    'etapes'  => $etapes,
                ];
            }
            $etapes[] = 'Schéma complet appliqué et vérifié (' . count($attendues) . ' tables).';

            // 4. Compte administrateur (rôle « administration », droits maximaux).
            $stmt = $pdo->prepare(
                'INSERT INTO utilisateurs (nom, email, password_hash, role, actif)
                 VALUES (:nom, :email, :hash, \'administration\', 1)'
            );
            $stmt->execute([
                'nom'   => mb_substr($admin['nom'], 0, 120),
                'email' => mb_substr($admin['email'], 0, 190),
                'hash'  => password_hash($admin['motdepasse'], PASSWORD_DEFAULT),
            ]);
            $etapes[] = 'Compte administrateur créé.';

            $pdo = null;
        } catch (\PDOException $e) {
            Logger::error(sprintf('Installation en echec (code %d)', (int) ($e->errorInfo[1] ?? 0)));
            $pdo = null;

            return [
                'succes'  => false,
                'message' => self::messageErreurPdo($e, 'installation'),
                'etapes'  => $etapes,
            ];
        }

        // 5. Écriture de la configuration (identifiants en clair sur disque,
        //    d'où les permissions 0600 et le classement hors zone servie).
        if (!self::ecrireConfig($db)) {
            return [
                'succes'  => false,
                'message' => 'Base de données installée, mais écriture de config/database.php impossible. '
                    . 'Vérifiez les droits du dossier config/ puis relancez scripts/install.php.',
                'etapes'  => $etapes,
            ];
        }
        $etapes[] = 'Fichier de configuration écrit (permissions 0600).';

        /*
         * 6. Jeton personnel GitHub — facultatif.
         *
         * Aucun fichier n'est créé si aucun jeton n'est fourni : le contrôle des
         * mises à jour reste alors fonctionnel en accès anonyme, sur un dépôt
         * public, et l'absence de fichier est le comportement normal plutôt
         * qu'un fichier vide à interpréter.
         *
         * Cet emplacement est choisi parce que le module de sauvegarde exporte
         * toute la base dans une archive téléchargeable : un jeton stocké dans
         * `parametres` y donnerait accès aux dépôts du projet.
         */
        $jeton = mb_substr(trim((string) ($admin['github_token'] ?? '')), 0, 255);
        if ($jeton !== '') {
            if (!self::ecrireSecrets($jeton)) {
                return [
                    'succes'  => false,
                    'message' => 'Base installée, mais écriture de config/secrets.php impossible. '
                        . 'Vérifiez les droits du dossier config/ puis relancez scripts/install.php.',
                    'etapes'  => $etapes,
                ];
            }
            $etapes[] = 'Jeton GitHub enregistré (config/secrets.php, permissions 0640).';
        } else {
            $etapes[] = 'Recherche de mise à jour GitHub en accès anonyme (60 requêtes/heure).';
        }

        // 7. Verrouillage : point de non-retour de l'assistant web.
        $version = (string) ((require dirname(__DIR__) . '/config/config.php')['app']['version'] ?? '1.0.0');
        if (!InstallState::verrouiller($version)) {
            return [
                'succes'  => false,
                'message' => 'Base installée, mais le fichier de verrouillage storage/install.lock n\'a pas pu être écrit. '
                    . 'L\'assistant resterait accessible : créez ce fichier manuellement avant toute mise en service.',
                'etapes'  => $etapes,
            ];
        }
        $etapes[] = 'Verrou d\'installation écrit (storage/install.lock).';

        return ['succes' => true, 'message' => 'Installation terminée.', 'etapes' => $etapes];
    }

    /** Importe `sql/schema.sql`. */
    public static function appliquerSchema(\PDO $pdo): void
    {
        $chemin = dirname(__DIR__) . '/sql/schema.sql';
        $sql    = @file_get_contents($chemin);
        if ($sql === false) {
            throw new \RuntimeException('Fichier sql/schema.sql introuvable ou illisible.');
        }
        $pdo->exec($sql);
    }

    /** Tables attendues après installation (contrôle de recette de l'assistant). */
    public static function tablesAttendues(): array
    {
        $chemin = dirname(__DIR__) . '/sql/schema.sql';
        $sql    = @file_get_contents($chemin);
        if ($sql === false) {
            return [];
        }
        preg_match_all('/^CREATE TABLE\s+(\w+)/mi', (string) $sql, $m);

        return $m[1] ?? [];
    }

    /**
     * Écrit `config/database.php` en 0600 via une écriture atomique.
     * Les valeurs sont échappées par `var_export` : aucune injection possible
     * dans le fichier généré, y compris pour un mot de passe contenant un apostrophe.
     */
    public static function ecrireConfig(array $db): bool
    {
        $contenu = "<?php\ndeclare(strict_types=1);\n\n"
            . "/**\n"
            . " * Configuration de connexion MySQL / MariaDB.\n"
            . " * Générée par l'assistant d'installation le " . date('d/m/Y H:i:s') . ".\n"
            . " * Fichier confidentiel : permissions 0600, hors racine servie par Apache.\n"
            . " * Ne jamais versionner ce fichier (exclu de .aiexclude.md).\n"
            . " */\n\n"
            . 'return ' . var_export([
                'host'     => (string) $db['host'],
                'port'     => (int) $db['port'],
                'database' => (string) $db['database'],
                'username' => (string) $db['username'],
                'password' => (string) $db['password'],
                'charset'  => 'utf8mb4',
            ], true) . ";\n";

        return InstallState::ecrireAtomique(InstallState::configPath(), $contenu, 0600);
    }

    /**
     * Écrit `config/secrets.php` avec le jeton GitHub.
     *
     * `0640` et non `0600` : PHP s'exécute sous `www-data`, alors que le fichier
     * appartient au compte qui a lancé l'installation. En `0600`, le groupe ne
     * pourrait pas le lire et le contrôle des mises à jour retomberait en accès
     * anonyme **sans message** — l'application fonctionnerait, mais avec un quota
     * réduit. Voir les commandes `chown` / `chmod` du README.
     *
     * La valeur est échappée par `var_export` : aucune injection n'est possible
     * dans le fichier généré, y compris pour un jeton contenant une apostrophe.
     */
    public static function ecrireSecrets(string $jeton): bool
    {
        $contenu = "<?php\ndeclare(strict_types=1);\n\n"
            . "/**\n"
            . " * Secrets de l'application.\n"
            . " * Généré par l'assistant d'installation le " . date('d/m/Y H:i:s') . ".\n"
            . " *\n"
            . " * Permissions 0640, groupe www-data : PHP doit pouvoir le lire sous\n"
            . " * ce compte, alors que le fichier appartient au compte d'installation.\n"
            . " * Hors racine servie par Apache (public/.htaccess répond 403 sur\n"
            . " * config/) et exclu du dépôt par .gitignore.\n"
            . " *\n"
            . " * La variable d'environnement FLOTTEO_GITHUB_TOKEN prime sur ce\n"
            . " * fichier. Videz la chaîne pour revenir en accès anonyme.\n"
            . " */\n\n"
            . 'return ' . var_export(['github_token' => $jeton], true) . ";\n";

        return InstallState::ecrireAtomique(InstallState::secretsPath(), $contenu, 0640);
    }

    /**
     * Valide les paramètres de connexion.
     *
     * @return string[] liste de messages d'erreur, vide si tout est correct
     */
    public static function validerParametres(array $db): array
    {
        $erreurs = [];
        $hote    = trim((string) ($db['host'] ?? ''));
        $base    = trim((string) ($db['database'] ?? ''));
        $user    = trim((string) ($db['username'] ?? ''));

        if ($hote === '') {
            $erreurs[] = 'L\'hôte du serveur est obligatoire.';
        }
        $port = (int) ($db['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            $erreurs[] = 'Le port doit être compris entre 1 et 65535.';
        }
        if ($base === '') {
            $erreurs[] = 'Le nom de la base de données est obligatoire.';
        } elseif (preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $base) !== 1) {
            $erreurs[] = 'Le nom de la base ne peut contenir que des lettres, chiffres, tirets et soulignés.';
        }
        if ($user === '') {
            $erreurs[] = 'L\'utilisateur MySQL est obligatoire.';
        }

        return $erreurs;
    }

    /**
     * Valide le compte administrateur initial.
     *
     * @return string[]
     */
    public static function validerAdmin(array $admin): array
    {
        $erreurs = [];
        $nom     = trim((string) ($admin['nom'] ?? ''));
        $email   = trim((string) ($admin['email'] ?? ''));
        $mdp     = (string) ($admin['motdepasse'] ?? '');

        if ($nom === '') {
            $erreurs[] = 'Le nom de l\'administrateur est obligatoire.';
        } elseif (mb_strlen($nom) > 120) {
            $erreurs[] = 'Le nom de l\'administrateur est trop long (120 caractères maximum).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = 'L\'adresse email de l\'administrateur est invalide.';
        }
        if (strlen($mdp) < self::MdpMin) {
            $erreurs[] = sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MdpMin);
        } elseif (!preg_match('/[a-z]/', $mdp) || !preg_match('/[A-Z]/', $mdp) || !preg_match('/\d/', $mdp)) {
            $erreurs[] = 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.';
        }

        return $erreurs;
    }
}