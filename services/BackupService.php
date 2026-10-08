<?php
declare(strict_types=1);

namespace Services;

use Core\Logger;
use Models\Backup;

/**
 * Moteur de sauvegarde : export de la base, compression des fichiers déposés,
 * conditionnement en une archive unique et rotation par ancienneté.
 *
 * **Pourquoi un export PDO et non `mysqldump`.** L'outil externe exigerait le
 * mot de passe de la base, transmis soit sur la ligne de commande — lisible par
 * tout processus du serveur pendant l'exécution — soit par un fichier
 * temporaire que rien n'oblige à effacer. L'export par PDO n'expose aucun
 * secret, ne dépend d'aucun binaire présent sur la machine, et s'exécute dans
 * le même processus que l'application.
 *
 * L'archive produite contient :
 *  - `donnees/flotteo.sql` : structure (DDL) puis données (DML) de toutes les
 *    tables, transaction enveloppante comprise ;
 *  - `uploads/…` : l'intégralité de `public/uploads/`, arborescence conservée ;
 *  - `manifeste.json` : horodatage, version, empreintes, volumétrie.
 *
 * Le tout est conditionné dans une archive ZIP horodatée, déposée dans
 * `storage/backups/` — répertoire **hors de la racine servie par Apache** : une
 * sauvegarde n'est de toute façon pas une ressource publique.
 */
final class BackupService
{
    /** Préfixe des archives produites. */
    private const PREFIXE = 'flotteo_backup_';

    private const DOSSIER_DONNEES  = 'donnees';
    private const DOSSIER_FICHIERS = 'uploads';

    /** Compression : `ZipArchive` est en Level 6, bon compromis taille/vitesse. */
    private const NIVEAU_COMPRESSION = 6;

    /** Répertoire de stockage, hors racine web. */
    public static function dossier(): string
    {
        return dirname(__DIR__) . '/storage/backups';
    }

    /** Vrai si le répertoire de stockage existe et est inscriptible. */
    public static function stockagePret(): bool
    {
        return is_dir(self::dossier()) && is_writable(self::dossier());
    }

    /**
     * Prépare le répertoire de stockage.
     *
     * Un `.htaccess` y interdit toute lecture : le dossier est déjà hors racine
     * servie, la directive protège d'un déplacement ultérieur de la racine web.
     */
    public static function preparerStockage(): bool
    {
        $dossier = self::dossier();
        if (!is_dir($dossier) && !@mkdir($dossier, 0770, true) && !is_dir($dossier)) {
            Logger::error("Repertoire de sauvegarde inaccessible: $dossier");
            return false;
        }

        $protege = $dossier . '/.htaccess';
        if (!is_file($protege)) {
            @file_put_contents($protege, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
            @chmod($protege, 0644);
        }

        return is_writable($dossier);
    }

    /** Nom d'archive horodatée, à la minute près. */
    public static function nomArchive(?int $instant = null): string
    {
        return self::PREFIXE . date('Y-m-d_Hi', $instant ?? time()) . '.zip';
    }

    /**
     * Produit une archive complète.
     *
     * Le retour est un tableau décrit, jamais une exception : l'appelant — page
     * d'administration ou tâche cron — doit pouvoir afficher un message et
     * poursuivre. Seules les erreurs d'infrastructure lèvent, car elles
     * impliquent un état incohérent qu'aucune reprise automatique ne corrige.
     *
     * @return array{ok: bool, message: string, fichier: ?string, taille: int, tables: int, fichiers: int}
     */
    public static function generer(bool $externaliser = true): array
    {
        if (!self::preparerStockage()) {
            return self::echec('Répertoire de stockage des sauvegardes inaccessible : ' . self::dossier());
        }

        if (!class_exists(\ZipArchive::class)) {
            return self::echec('Extension PHP « zip » absente : impossible de conditionner l\'archive.');
        }

        $nom = self::nomArchive();

        // `OVERWRITE` ne s'applique qu'au nom calculé, et ce nom est horodaté à
        // la minute : deux sauvegardes demandées dans la même minute se
        // chevaucheraient. Le suffixe garantit qu'aucune archive n'est jamais
        // remplacée, même dans ce cas.
        for ($suffixe = 1; is_file(self::dossier() . '/' . $nom) && $suffixe < 100; $suffixe++) {
            $nom = sprintf('%s%d.zip', self::PREFIXE . date('Y-m-d_Hi'), $suffixe);
        }
        $chemin = self::dossier() . '/' . $nom;
        $archive = new \ZipArchive();
        $archive->open($chemin, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        try {
            $sql = self::exportBase();
            $archive->addFromString(self::DOSSIER_DONNEES . '/flotteo.sql', $sql);
            $tables = substr_count($sql, 'DROP TABLE IF EXISTS');

            $fichiers = self::ajouterUploads($archive);
            $archive->addFromString('manifeste.json', self::manifeste($nom, $tables, $fichiers));
            $archive->close();
        } catch (\Throwable $e) {
            Logger::error('Sauvegarde interrompue', $e);
            @$archive->close();
            @unlink($chemin);

            return self::echec('Sauvegarde interrompue : ' . $e->getMessage());
        }

        $taille = (int) @filesize($chemin);
        if ($taille <= 0) {
            @unlink($chemin);
            return self::echec('Archive produite vide : sauvegarde considérée comme en échec.');
        }

        $id = self::enregistrer($nom, $taille, $tables, $fichiers);

        return [
            'ok'       => true,
            'message'  => 'Archive ' . $nom . ' produite (' . SambaClient::poids($taille) . ').',
            'fichier'  => $nom,
            'taille'   => $taille,
            'tables'   => $tables,
            'fichiers' => $fichiers,
            'id'       => $id,
        ] + ($externaliser ? self::externaliser($id, $chemin) : []);
    }

    /**
     * Copie une archive déjà produite vers le partage configuré et consigne le
     * verdict. L'échec réseau ne détruit pas la sauvegarde locale.
     *
     * @return array{ok: bool, message: string, samba: string}
     */
    public static function externaliser(int $id, string $chemin): array
    {
        $reglages = Backup::reglages();
        if ((string) $reglages['sauvegarde_partage_actif'] !== '1') {
            Backup::majSamba($id, 'non_configure', null);
            Logger::info("Sauvegarde $id produite, externalisation desactivee");

            return ['ok' => true, 'message' => 'Externalisation désactivée : archive conservée localement.', 'samba' => 'non_configure'];
        }

        $client = new SambaClient($reglages);
        $verdict = $client->deposer($chemin);

        Backup::majSamba($id, $verdict['ok'] ? 'reussi' : 'echec', $verdict['ok'] ? null : $verdict['message']);

        return [
            'ok'      => true,
            'message' => $verdict['message'],
            'samba'   => $verdict['ok'] ? 'reussi' : 'echec',
        ];
    }

    // ------------------------------------------------------------------ export

    /**
     * Export SQL complet : structure puis données, table par table.
     *
     * Chaque table est enclosures dans sa propre instruction `LOCK`/`UNLOCK`,
     * l'ensemble du fichier dans une transaction : une restauration est donc
     * atomique, et un fichier interrompu ne produit pas une base à moitié
     * restaurée lorsque `autocommit` est désactivé.
     */
    private static function exportBase(): string
    {
        $lignes = [
            '-- Export Flotteo du ' . date('d/m/Y H:i:s'),
            '-- Structure puis données de toutes les tables.',
            '-- Restauration : mysql -u <user> -p <base> < flotteo.sql',
            '',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS = 0;',
            'SET SQL_MODE = \'NO_AUTO_VALUE_ON_ZERO\';',
            '',
            'START TRANSACTION;',
            '',
        ];

        $tables = self::tables();
        foreach ($tables as $table) {
            $lignes[] = self::exportTable($table);
        }

        $lignes[] = 'COMMIT;';
        $lignes[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $lignes[] = '';

        return implode("\n", $lignes);
    }

    /**
     * Export d'une table : `DROP`/`CREATE` puis les `INSERT`.
     *
     * @return string
     */
    private static function exportTable(string $table): string
    {
        $pdo  = \Core\Database::pdo();
        $base = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $nom  = self::identifiant($table);

        $ddl = (string) $pdo->query('SHOW CREATE TABLE ' . $nom)->fetch(\PDO::FETCH_NUM)[1];

        $sortie = [
            '-- ----------------------------------------------------------',
            '-- Table ' . $table,
            '-- ----------------------------------------------------------',
            'DROP TABLE IF EXISTS ' . $nom . ';',
            $ddl . ';',
            '',
        ];

        $lignes = $pdo->query('SELECT * FROM ' . $nom, \PDO::FETCH_ASSOC);
        $donnees = [];
        while (($ligne = $lignes->fetch()) !== false) {
            $donnees[] = self::instructionInsert($ligne);
        }

        if ($donnees !== []) {
            $sortie[] = 'LOCK TABLES ' . $nom . ' WRITE;';
            $sortie[] = 'INSERT INTO ' . $nom . ' VALUES';
            $sortie[] = implode(",\n", $donnees) . ';';
            $sortie[] = 'UNLOCK TABLES;';
        } else {
            $sortie[] = '-- (table vide)';
        }
        $sortie[] = '';

        return implode("\n", $sortie);
    }

    /** @param array<string, mixed> $ligne */
    private static function instructionInsert(array $ligne): string
    {
        $pdo = \Core\Database::pdo();
        $valeurs = array_map(
            static fn (mixed $v): string => $v === null ? 'NULL' : $pdo->quote((string) $v),
            array_values($ligne)
        );

        return '  (' . implode(', ', $valeurs) . ')';
    }

    /**
     * Tables de la base, hors vues.
     *
     * Le nom est validé avant toute composition de requête : il provient d'une
     * interrogation du serveur, jamais d'une saisie, mais la validation est ce
     * qui rend l'interpolation impossible par construction.
     *
     * @return list<string>
     */
    private static function tables(): array
    {
        $pdo   = \Core\Database::pdo();
        $noms  = $pdo->query('SHOW FULL TABLES')->fetchAll(\PDO::FETCH_NUM);
        $liste = [];

        foreach ($noms as $ligne) {
            // `SHOW FULL TABLES` renvoie (nom, type) : on écarte les vues, dont
            // le `SHOW CREATE TABLE` n'est pas l'export attendu.
            if ((string) ($ligne[1] ?? '') !== 'BASE TABLE') {
                continue;
            }
            $table = (string) $ligne[0];
            if (self::identifiantValide($table)) {
                $liste[] = $table;
            }
        }

        sort($liste);

        return $liste;
    }

    private static function identifiantValide(string $nom): bool
    {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/', $nom) === 1;
    }

    /** Nom de table encadré pour la composition d'une requête. */
    private static function identifiant(string $table): string
    {
        if (!self::identifiantValide($table)) {
            throw new \RuntimeException('Nom de table non valide : ' . $table);
        }

        return '`' . $table . '`';
    }

    /**
     * Ajoute `public/uploads/` à l'archive, arborescence conservée.
     *
     * @return int nombre de fichiers ajoutés
     */
    private static function ajouterUploads(\ZipArchive $archive): int
    {
        $racine = rtrim((string) (require dirname(__DIR__) . '/config/config.php')['uploads']['chemin'], '/');
        if (!is_dir($racine)) {
            return 0;
        }

        $ajoutes = 0;
        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $longueur = strlen($racine) + 1;
        foreach ($iterateur as $entree) {
            /** @var \SplFileInfo $entree */
            if (!$entree->isFile() || !$entree->isReadable()) {
                continue;
            }
            // Un lien symbolique pourrait faire sortir l'archive de `uploads/` :
            // seuls les fichiers réguliers entrant dans le conditionnement.
            if (!$entree->isLink()) {
                $relatif = substr($entree->getPathname(), $longueur);
                if ($archive->addFile($entree->getPathname(), self::DOSSIER_FICHIERS . '/' . $relatif)) {
                    $ajoutes++;
                }
            }
        }

        return $ajoutes;
    }

    /** @return string JSON du manifeste, lisible sans le reste de l'archive. */
    private static function manifeste(string $nom, int $tables, int $fichiers): string
    {
        $conf = (require dirname(__DIR__) . '/config/config.php');

        return (string) json_encode([
            'application'    => $conf['app']['nom'],
            'version'        => $conf['app']['version'],
            'archive'        => $nom,
            'genere_le'      => date('c'),
            'base'           => self::base(),
            'tables'         => $tables,
            'fichiers'       => $fichiers,
            'contenu'        => [
                self::DOSSIER_DONNEES . '/flotteo.sql' => 'Export SQL complet (structure et données)',
                self::DOSSIER_FICHIERS . '/'              => 'Copie de public/uploads/',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function base(): string
    {
        return (string) \Core\Database::scalar('SELECT DATABASE()', [], '');
    }

    private static function enregistrer(string $nom, int $taille, int $tables, int $fichiers): int
    {
        try {
            return Backup::enregistrer([
                'fichier'         => $nom,
                'taille'          => $taille,
                'empreinte'       => hash_file('sha256', self::dossier() . '/' . $nom) ?: '',
                'tables_dump'     => $tables,
                'fichiers_inclus' => $fichiers,
                'samba_statut'    => 'non_configure',
                'samba_message'   => null,
            ]);
        } catch (\PDOException $e) {
            Logger::error("Enregistrement de la sauvegarde $nom impossible", $e);
            return 0;
        }
    }

    // ---------------------------------------------------------------- rotation

    /**
     * Supprime les archives plus anciennes que la durée de conservation.
     *
     * Seules les archives **présentes sur le disque** sont retirées : une ligne
     * d'historique sans fichier est nettoyée séparément, faute de quoi une
     * archive déjà effacée par un tiers bloquerait la rotation à chaque exécution.
     *
     * @return array{ok: bool, message: string, supprimees: int, liberes: int}
     */
    public static function purger(): array
    {
        if (!self::preparerStockage()) {
            return [
                'ok' => false, 'message' => 'Répertoire de stockage inaccessible.', 'supprimees' => 0, 'liberes' => 0,
            ];
        }

        $jours  = Backup::retentionJours();
        $limite = strtotime('-' . $jours . ' days');
        $dossier = self::dossier();
        $supprimees = 0;
        $liberes    = 0;

        foreach ((array) @scandir($dossier) as $entree) {
            if (!is_string($entree) || !str_starts_with($entree, self::PREFIXE) || !str_ends_with($entree, '.zip')) {
                continue;
            }
            $chemin = $dossier . '/' . $entree;
            if (!is_file($chemin) || (int) @filemtime($chemin) >= $limite) {
                continue;
            }

            $taille = (int) @filesize($chemin);
            if (@unlink($chemin)) {
                $supprimees++;
                $liberes += $taille;
                Logger::info("Rotation: archive $entree supprimee (plus de $jours jours)");
            }
        }

        // Lignes d'historique sans archive : elles occuperaient l'historique
        // d'un fichier qui n'existe plus et fausseraient le décompte affiché.
        $orphelines = self::purgerHistoriqueOrphelin();

        $message = $supprimees === 0
            ? "Aucune archive à supprimer : tout est conservé depuis moins de $jours jours."
            : sprintf(
                '%d archive%s supprimée%s (%s libéré%s, conservation %d jours%s).',
                $supprimees,
                $supprimees > 1 ? 's' : '',
                $supprimees > 1 ? 's' : '',
                SambaClient::poids($liberes),
                $liberes > 0 ? 's' : '',
                $jours,
                $orphelines > 0 ? sprintf(', %d ligne%s d\'historique orpheline%s retirée%s', $orphelines,
                    $orphelines > 1 ? 's' : '', $orphelines > 1 ? 's' : '', $orphelines > 1 ? 's' : '') : ''
            );

        return ['ok' => true, 'message' => $message, 'supprimees' => $supprimees, 'liberes' => $liberes];
    }

    /** Retire les lignes d'historique dont le fichier n'existe plus. */
    private static function purgerHistoriqueOrphelin(): int
    {
        $dossier = self::dossier();
        $retires = 0;

        foreach (Backup::historique(500) as $ligne) {
            $fichier = (string) $ligne['fichier'];
            if (self::estNomArchive($fichier) && !is_file($dossier . '/' . $fichier)) {
                Backup::supprimer((int) $ligne['id']);
                $retires++;
            }
        }

        return $retires;
    }

    // ------------------------------------------------------------------ fichiers

    /** Vrai si `nom` est un nom d'archive produit par ce service. */
    public static function estNomArchive(string $nom): bool
    {
        return preg_match('/^' . self::PREFIXE . '\d{4}-\d{2}-\d{2}_\d{4}\.zip$/', $nom) === 1;
    }

    /**
     * Chemin absolu d'une archive, ou null si le nom n'est pas recevable.
     *
     * Le nom est validé **et** l'archive doit exister : c'est ce qui empêche
     * qu'une valeur issue du lien de téléchargement désigne un autre fichier.
     */
    public static function cheminArchive(string $nom): ?string
    {
        if (!self::estNomArchive($nom)) {
            return null;
        }
        $chemin = self::dossier() . '/' . $nom;

        return is_file($chemin) ? $chemin : null;
    }

    /** Taille totale occupée par les archives, en octets. */
    public static function volume(): int
    {
        $total = 0;
        foreach ((array) @scandir(self::dossier()) as $entree) {
            if (is_string($entree) && str_starts_with($entree, self::PREFIXE)) {
                $total += (int) @filesize(self::dossier() . '/' . $entree);
            }
        }

        return $total;
    }

    /**
     * Contexte d'affichage de la section Paramètres : historique, volumétrie et
     * état du partage réseau.
     *
     * Un seul point de rassemblement : la vue ne connaît ni le service, ni la
     * localisation du répertoire de stockage.
     *
     * @return array{
     *     historique: array<int, array<string, mixed>>,
     *     volume: int,
     *     retention: int,
     *     stockage: bool,
     *     partageActif: bool,
     *     partage: string,
     *     transport: ?string,
     *     invite: bool,
     *     transportMessage: string
     * }
     */
    public static function contexte(): array
    {
        $client    = new SambaClient();
        $disponible = $client->disponible();

        return [
            'historique'      => Backup::historique(50),
            'volume'          => self::volume(),
            'retention'       => Backup::retentionJours(),
            'stockage'        => self::preparerStockage(),
            'partageActif'    => (string) Backup::reglage('sauvegarde_partage_actif', '0') === '1',
            'partage'         => $client->partage(),
            'transport'       => $client->transport(),
            'invite'          => $client->estInvite(),
            'transportMessage' => $disponible ? '' : $client->dernierMessage(),
        ];
    }

    /** @return array{ok: bool, message: string} */
    private static function echec(string $message): array
    {
        Logger::error('Sauvegarde : ' . $message);

        return [
            'ok'       => false,
            'message'  => $message,
            'fichier'  => null,
            'taille'   => 0,
            'tables'   => 0,
            'fichiers' => 0,
        ];
    }
}