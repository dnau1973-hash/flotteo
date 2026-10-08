<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Core\Database;
use Core\Flash;
use Core\Logger;
use Core\Mailer;
use Core\Request;
use Core\SmtpClient;
use Models\Backup;

/**
 * Paramétrage système de l'application.
 *
 * La page est découpée en sections servies par une sous-navigation verticale ;
 * chaque section possède son propre envoi, ce qui évite qu l'enregistrement de
 * la messagerie n'écrase les alertes et réciproquement.
 *
 * Sections :
 *  - `messagerie` : mode d'envoi (frontal natif ou serveur SMTP), paramètres du
 *    serveur, authentification, adresse expéditrice et test d'envoi ;
 *  - `alertes`    : activation, paliers d'anticipation, destinataire des rappels,
 *    envoi manuel et tâche planifiée ;
 *  - `sauvegardes` : rétention des archives, partage réseau Samba, exécution ;
 *  - `mises-a-jour` : dépôt GitHub de référence et recherche des versions
 *    publiées, avec relevé du dernier contrôle.
 */
final class ParamController extends Controller
{
    /** Section servie lorsqu'aucune n'est demandée ou que la demande est inconnue. */
    private const SECTION_DEFAUT = 'messagerie';

    /**
     * Sections de la page et clés qu'elles détiennent.
     *
     * La répartition des clés est structurante : `save()` n'écrit que celles de
     * la section visée, jamais toutes.
     *
     * @var array<string, array{libelle: string, icone: string, resume: string, cles: string[]}>
     */
    private const SECTIONS = [
        'messagerie' => [
            'libelle' => 'Messagerie',
            'icone'   => 'envelope',
            'resume'  => 'Mode d\'envoi, serveur SMTP et adresse expéditrice.',
            'cles'    => [
                'mail_transport', 'smtp_host', 'smtp_port', 'smtp_chiffrement',
                'smtp_user', 'smtp_password', 'email_expediteur',
            ],
        ],
        'alertes' => [
            'libelle' => 'Alertes',
            'icone'   => 'bell',
            'resume'  => 'Paliers d\'anticipation et destinataire des rappels.',
            'cles'    => [
                'email_gestionnaire', 'alerte_palier_1', 'alerte_palier_2',
                'alerte_palier_3', 'alerte_active',
            ],
        ],
        'kilometrage' => [
            'libelle' => 'Relevés kilométriques',
            'icone'   => 'gauge-high',
            'resume'  => 'Fréquence, délai de grâce et relances automatiques.',
            'cles'    => [
                'km_recurrence', 'km_destinataire_type', 'km_email_fixe',
                'km_delai_jours_relance', 'km_relance_active',
            ],
        ],
        'sauvegardes' => [
            'libelle' => 'Sauvegardes',
            'icone'   => 'box-archive',
            'resume'  => 'Conservation des archives et copie vers un partage réseau.',
            'cles'    => [
                'sauvegarde_retention_jours', 'sauvegarde_partage_actif', 'samba_hote',
                'samba_partage', 'samba_repertoire', 'samba_utilisateur',
                'samba_mot_de_passe', 'samba_domaine',
            ],
        ],
        'mises-a-jour' => [
            'libelle' => 'Mises à jour',
            'icone'   => 'arrow-up',
            'resume'  => 'Dépôt GitHub de référence et recherche des versions publiées.',
            'cles'    => ['depot_github'],
        ],
        'base' => [
            'libelle' => 'Base de données',
            'icone'   => 'database',
            'resume'  => 'Informations sur les tables et opérations de nettoyage.',
            'cles'    => [],
        ],
    ];

    /** Valeurs appliquées à une clé absente de la base. */
    private const DEFAUTS = [
        'mail_transport'    => 'mail',
        'smtp_host'         => '',
        'smtp_port'         => '587',
        'smtp_chiffrement'  => 'tls',
        'smtp_user'         => '',
        'smtp_password'     => '',
        'email_expediteur'  => 'no-reply@flotteo.local',
        'email_gestionnaire' => '',
        'alerte_palier_1'   => '90',
        'alerte_palier_2'   => '180',
        'alerte_palier_3'   => '270',
        'alerte_active'     => '1',
        'sauvegarde_retention_jours' => '30',
        'sauvegarde_partage_actif'    => '0',
        'samba_hote'                 => '',
        'samba_partage'              => '',
        'samba_repertoire'           => '',
        'samba_utilisateur'          => '',
        'samba_mot_de_passe'         => '',
        'samba_domaine'              => '',
        'depot_github'               => '',
        'km_recurrence'              => 'mensuelle',
        'km_destinataire_type'       => 'gestionnaire',
        'km_email_fixe'              => '',
        'km_delai_jours_relance'     => '3',
        'km_relance_active'          => '1',
    ];

    /** Section demandée, ramenée à une valeur exploitable. */
    private function section(): string
    {
        $sectione = (string) (Request::param('section') ?: Request::input('section', ''));

        return isset(self::SECTIONS[$sectione]) ? $sectione : self::SECTION_DEFAUT;
    }

    /** Raccourci vers la section Sauvegardes des paramètres. */
    public function sauvegardes(): void
    {
        $this->guard(Auth::ROLE_ADMIN);
        $this->redirect('/admin/parametres/sauvegardes');
    }

    public function index(): void
    {
        $this->guard(Auth::ROLE_ADMIN);
        $section = $this->section();

        if ($section === 'messagerie' || $section === 'sauvegardes') {
            // Le pied de page préfixe déjà « js/ » (Url::asset('js/' . $script)) :
            // le nom transmis doit donc être relatif à ce dossier.
            $this->view()->useScript('parametres.js');
        }

        $this->render('admin/parametres', [
            'base_url'  => $this->baseUrl(),
            'sections'  => self::SECTIONS,
            'section'   => $section,
            'valeurs'   => $this->valeurs(),
            'paliers'   => \Services\AlertService::paliers(),
            // La section Sauvegardes affiche l'historique et la volumétrie : les
            // produire pour toutes les sections interrogerait le disque à chaque
            // ouverture des Paramètres, y compris pour la messagerie.
            'sauvegardes' => $section === 'sauvegardes' ? \Services\BackupService::contexte() : [],
            // La section Mises à jour n'interroge jamais GitHub à l'affichage :
            // elle relit le dernier relevé mémorisé. Un appel à l'API à chaque
            // ouverture serait lent, coûteux en quota et bloquerait la page
            // quand le réseau sortant est coupé.
            'misesAJour' => $section === 'mises-a-jour' ? $this->miseAJour() : [],
        ]);
    }

    /**
     * Contexte de la section Mises à jour, sans accès réseau.
     *
     * @return array{version: string, depot: string, releve: array<string, mixed>, etat: string, message: string}
     */
    private function miseAJour(): array
    {
        $conf = require dirname(__DIR__) . '/config/config.php';

        $depot = (string) Mailer::param('depot_github', '');
        $releve = self::releveLu();
        $comparaison = \Services\GithubClient::comparer((string) $conf['app']['version'], $releve);

        return [
            'version'  => (string) $conf['app']['version'],
            'depot'    => $depot,
            'releve'   => $releve,
            'etat'     => $comparaison['etat'],
            'message'  => $comparaison['message'],
        ];
    }

    /**
     * Dernier relevé mémorisé, tel qu'il a été écrit par `verifier()`.
     *
     * @return array<string, mixed>
     */
    private static function releveLu(): array
    {
        $brut = (string) Mailer::param('depot_github_releve', '');
        if ($brut === '') {
            return [];
        }

        $donnees = json_decode($brut, true);

        return is_array($donnees) ? $donnees : [];
    }

    /**
     * Enregistrement d'une section (POST).
     *
     * Seules les clés de la section demandée sont écrites : un envoi partiel ne
     * peut donc pas neutraliser une autre section.
     */
    public function save(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);
        $section = $this->section();

        try {
            $ecrites = [];
            $stockees = $this->valeurs();
            foreach (self::SECTIONS[$section]['cles'] as $cle) {
                $valeur = $this->valeurAEnregistrer($cle, $stockees);

                $anomalie = self::controler($cle, $valeur);
                if ($anomalie !== null) {
                    Flash::add('danger', $anomalie);
                    $this->redirect('/admin/parametres/' . $section);
                }

                $this->ecrire($cle, $valeur);
                $ecrites[$cle] = $valeur;
            }

            Flash::add('success', 'Paramètres « ' . self::SECTIONS[$section]['libelle'] . ' » enregistrés.');
        } catch (\PDOException $e) {
            Logger::error('Enregistrement des paramètres impossible', $e);
            Flash::add('danger', 'Enregistrement des paramètres impossible.');
        }

        $this->redirect('/admin/parametres/' . $section);
    }

    /**
     * Valeur à persister pour une clé, après traitement propre à la clé.
     *
     * Deux cas ne se lisent pas dans le formulaire lui-même :
     *  - `alerte_active` est un interrupteur : une case décochée n'est pas
     *    soumise du tout, l'absence vaut donc désactivation ;
     *  - `smtp_password` n'est jamais renvoyé par le formulaire. Un champ laissé
     *    vide conserve le mot de passe enregistré ; son effacement est une
     *    demande explicite, portée par la case à cocher dédiée.
     *
     * @param array<string, string> $stockees Valeurs actuellement en base
     */
    private function valeurAEnregistrer(string $cle, array $stockees): string
    {
        if ($cle === 'alerte_active' || $cle === 'sauvegarde_partage_actif') {
            return Request::input($cle, '') === '1' ? '1' : '0';
        }

        if ($cle === 'smtp_password' || $cle === 'samba_mot_de_passe') {
            if ((string) Request::input($cle . '_effacer', '') === '1') {
                return '';
            }
            $saisi = (string) Request::input($cle, '');

            return $saisi !== '' ? $saisi : ($stockees[$cle] ?? '');
        }

        return mb_substr((string) Request::input($cle, ''), 0, 190);
    }

    /**
     * Contrôle de validité d'une valeur.
     *
     * @return string|null Message d'anomalie, ou null si la valeur est recevable.
     */
    private static function controler(string $cle, string $valeur): ?string
    {
        if ($cle === 'email_expediteur') {
            if ($valeur === '') {
                return 'Renseignez l\'adresse expéditrice : aucun message ne peut partir sans elle.';
            }
            if (!filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
                return 'L\'adresse expéditrice n\'est pas une adresse valide.';
            }
        }

        if ($cle === 'email_gestionnaire') {
            if ($valeur !== '' && !filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
                return 'L\'adresse du gestionnaire destinataire n\'est pas valide.';
            }
        }

        if ($cle === 'mail_transport') {
            if (!in_array($valeur, Mailer::TRANSPORTS, true)) {
                return 'Mode d\'envoi inconnu : choisissez ' . self::listeDe(Mailer::TRANSPORTS) . '.';
            }
        }

        if ($cle === 'smtp_chiffrement') {
            if (!in_array($valeur, SmtpClient::CHIFFREMENTS, true)) {
                return 'Mode de chiffrement inconnu : choisissez ' . self::listeDe(SmtpClient::CHIFFREMENTS) . '.';
            }
        }

        if ($cle === 'smtp_port') {
            $port = (int) $valeur;
            if ($port < 1 || $port > 65535) {
                return 'Le port du serveur SMTP doit être compris entre 1 et 65535.';
            }
        }

        if (str_starts_with($cle, 'alerte_palier_')) {
            if ($valeur !== '' && (!ctype_digit($valeur) || (int) $valeur > 3650)) {
                return 'Un palier d\'anticipation doit être un nombre de jours compris entre 1 et 3650.';
            }
        }

        if ($cle === 'sauvegarde_retention_jours') {
            if (!ctype_digit($valeur) || (int) $valeur < Backup::RETENTION_MIN || (int) $valeur > Backup::RETENTION_MAX) {
                return 'La durée de conservation doit être un nombre de jours compris entre '
                    . Backup::RETENTION_MIN . ' et ' . Backup::RETENTION_MAX . '.';
            }
        }

        if ($cle === 'km_recurrence' && !in_array($valeur, ['mensuelle', 'hebdomadaire'], true)) {
            return 'La récurrence de saisie doit être « mensuelle » ou « hebdomadaire ».';
        }

        if ($cle === 'km_destinataire_type' && !in_array($valeur, ['gestionnaire', 'fixe'], true)) {
            return 'Type de destinataire des rappels kilométriques inconnu.';
        }

        if ($cle === 'km_email_fixe' && $valeur !== '' && !filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
            return 'L\'adresse email fixe pour les rappels kilométriques n\'est pas valide.';
        }

        if ($cle === 'km_delai_jours_relance' && (!ctype_digit($valeur) || (int) $valeur < 1 || (int) $valeur > 31)) {
            return 'Le délai de grâce avant relance doit être un nombre de jours compris entre 1 et 31.';
        }

        // Partage réseau : le contrôle est identique à celui du client, et fait
        // ici avant écriture pour qu'une configuration irrecevable ne soit
        // jamais persistée — la rejouer ensuite produirait le même échec.
        if ($cle === 'samba_hote' && $valeur !== '' && preg_match('/^[A-Za-z0-9._:-]{1,255}$/', $valeur) !== 1) {
            return 'L\'hôte Samba doit être un nom d\'hôte, une adresse IP ou un nom NetBIOS.';
        }

        if ($cle === 'samba_partage' && $valeur !== '' && preg_match('/^[A-Za-z0-9 _.-]{1,80}$/', $valeur) !== 1) {
            return 'Le nom du partage Samba contient des caractères non admis.';
        }

        if ($cle === 'samba_repertoire') {
            $chemin = trim($valeur, " \\/\t\n\r\0\x0B");
            if ($chemin !== '' && preg_match('#^[A-Za-z0-9 _.\-/]+$#', $chemin) !== 1) {
                return 'Le répertoire cible contient des caractères non admis.';
            }
        }

        if ($cle === 'samba_domaine' && $valeur !== '' && (string) Request::input('samba_utilisateur', '') === '') {
            return 'Un domaine ne peut être renseigné en accès invité : renseignez aussi un utilisateur, ou retirez le domaine.';
        }

        // Le dépôt est ensuite réécrit dans une URL d'API : il est contrôlé ici
        // pour qu'une saisie irrecevable ne soit jamais persistée.
        if ($cle === 'depot_github' && $valeur !== '' && \Services\GithubClient::validerDepot($valeur) === null) {
            return 'Le dépôt GitHub doit s\'écrire « compte/dépôt » : deux mots séparés par une seule barre oblique.';
        }

        return null;
    }

    /**
     * Énumère des valeurs en français pour un message d'erreur.
     *
     * « a », « a ou b », « a, b ou c » : l'accord du dernier élément change avec
     * le nombre d'options, qu'un simple assemblage ne restitue pas.
     *
     * @param string[] $valeurs
     */
    private static function listeDe(array $valeurs): string
    {
        $guillemets = array_map(static fn (string $v): string => '« ' . $v . ' »', $valeurs);
        $dernier = array_pop($guillemets);

        if ($guillemets === []) {
            return (string) $dernier;
        }

        return implode(', ', $guillemets) . ' ou ' . $dernier;
    }

    /**
     * Test d'envoi (POST JSON).
     *
     * Le serveur est éprouvé avec les valeurs actuellement saisies au formulaire,
     * et non avec celles enregistrées : l'utilisateur peut ainsi valider une
     * configuration avant de la persiste. Seul le mot de passe retombe sur la
     * valeur enregistrée, puisqu'il n'est jamais renvoyé par le formulaire.
     */
    public function testerCourriel(): void
    {
        if (!Csrf::check()) {
            $this->ko('Jeton de sécurité invalide. Rechargez la page.', 419);
        }
        if (!Auth::can(Auth::ROLE_ADMIN)) {
            $this->ko('Accès réservé aux administrateurs.', 403);
        }

        $destinataire = mb_substr((string) Request::input('test_destinataire', ''), 0, 190);
        if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            $this->ko('Renseignez une adresse de test valide.', 422);
        }

        $expediteur = mb_substr((string) Request::input('email_expediteur', ''), 0, 190);
        if (!filter_var($expediteur, FILTER_VALIDATE_EMAIL)) {
            $this->ko('Renseignez une adresse expéditrice valide avant de tester l\'envoi.', 422);
        }

        $motDePasse = (string) Request::input('smtp_password', '');
        if ($motDePasse === '') {
            $motDePasse = (string) $this->valeur('smtp_password', '');
        }

        try {
            $client = new SmtpClient(
                mb_substr((string) Request::input('smtp_host', ''), 0, 190),
                (int) Request::input('smtp_port', '587'),
                mb_substr((string) Request::input('smtp_chiffrement', 'tls'), 0, 20),
                mb_substr((string) Request::input('smtp_user', ''), 0, 190),
                $motDePasse
            );
        } catch (\InvalidArgumentException $e) {
            $this->ko($e->getMessage(), 422);
        }

        $conf = (require dirname(__DIR__) . '/config/config.php');
        $client->send(
            $expediteur,
            $destinataire,
            'Test d\'envoi — ' . $conf['app']['nom'],
            '<p>Ce message confirme que la messagerie de <strong>' . htmlspecialchars($conf['app']['nom'], ENT_QUOTES, 'UTF-8')
            . '</strong> est correctement configurée.</p>'
            . '<p>Il a été émis le ' . date('d/m/Y à H:i') . ' depuis un envoi déclenché manuellement.</p>'
        );

        if ($client->lastError() !== '') {
            $this->ko('Échec de l\'envoi de test : ' . $client->lastError(), 422);
        }

        $this->ok('Message de test envoyé à ' . $destinataire . '.');
    }

    /**
     * Recherche des mises à jour publiées sur GitHub (POST).
     *
     * Le dépôt éprouvé est celui **saisi au formulaire** lorsqu'il y en a un,
     * comme le test d'envoi le fait pour le serveur SMTP : l'administrateur peut
     * ainsi vérifier une correction avant de l'enregistrer. Le relevé est
     * mémorisé pour que l'affichage de la section n'ait jamais à interroger
     * l'API.
     */
    public function verifierMiseAJour(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        $saisi = trim((string) Request::input('depot_github', ''));
        $depot = $saisi !== '' ? $saisi : (string) Mailer::param('depot_github', '');

        if ($depot === '') {
            Flash::add('warning', 'Renseignez d\'abord le dépôt GitHub de référence.');
            $this->redirect('/admin/parametres/mises-a-jour');
        }

        $client = new \Services\GithubClient();
        $releve = $client->derniereVersion($depot);

        if (($releve['succes'] ?? false) === true) {
            $conf = require dirname(__DIR__) . '/config/config.php';
            $comparaison = \Services\GithubClient::comparer((string) $conf['app']['version'], $releve);

            $releve['etat'] = $comparaison['etat'];
            $releve['message'] = $comparaison['message'];
            $releve['version_installee'] = (string) $conf['app']['version'];
            $releve['consulte_le'] = date('Y-m-d H:i');

            $this->ecrire('depot_github_releve', (string) json_encode($releve, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $type = match ($comparaison['etat']) {
                'disponible' => 'warning',
                'a_jour'     => 'success',
                default      => 'info',
            };
            // Le dépôt est repris du relevé, donc sous sa forme normalisée : une
            // adresse GitHub complète saisie dans le champ est annoncée sous la
            // forme `compte/depot` qui a réellement été interrogée.
            Flash::add($type, 'Dépôt ' . (string) $releve['depot'] . ' : ' . $comparaison['message']);
        } else {
            // Un dépôt invalide efface le relevé précédent : conserver celui d'un
            // autre dépôt ferait afficher une version qui n'a rien à voir avec la
            // saisie en cours.
            $this->ecrire('depot_github_releve', (string) json_encode([
                'succes' => false,
                'depot'  => $depot,
                'message' => (string) $releve['message'],
                'consulte_le' => date('Y-m-d H:i'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            Flash::add('danger', (string) $releve['message']);
        }

        $this->redirect('/admin/parametres/mises-a-jour');
    }

    /** Affichage de la section base de données. */
    public function base(): void
    {
        $this->guard(Auth::ROLE_ADMIN);
        $this->render('admin/parametres/base', [
            'base_url' => $this->baseUrl(),
            'user'     => Auth::user(),
        ]);
    }

    /**
     * Tables exposées dans la section « Base de données ».
     *
     * Liste blanche unique, partagée par la consultation et la vidange : le nom
     * de table est interpolé dans les requêtes, il ne doit jamais provenir
     * d'ailleurs que de cette constante.
     */
    private const TABLES_BASE = [
        'utilisateurs',
        'parametres',
        'marques',
        'modeles',
        'entites',
        'loueurs',
        'lieux',
        'types_intervention',
        'vehicules',
        'maintenances',
        'maintenances_fichiers',
        'incidents',
        'incidents_fichiers',
        'sauvegardes',
        'releves_odometre',
    ];

    /**
     * Informations d'une table, en JSON (appel asynchrone de la section base).
     *
     * Répond toujours en JSON, y compris en cas d'erreur : la page affiche alors
     * un état « indisponible » au lieu de laisser tourner l'indicateur de
     * chargement indéfiniment.
     */
    public function tableInfo(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        $table = trim((string) Request::input('table', ''));
        if ($table === '') {
            $this->ko('Table non spécifiée.');
        }
        if (!in_array($table, self::TABLES_BASE, true)) {
            $this->ko('Table non autorisée.', 403);
        }

        try {
            // Volumétrie lue dans le catalogue : elle signale aussi une table
            // absente du schéma (migration non jouée), sans lever d'exception.
            $statut = Database::one(
                'SELECT DATA_LENGTH + INDEX_LENGTH AS taille
                   FROM INFORMATION_SCHEMA.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
                ['t' => $table]
            );
            if ($statut === null) {
                $this->ko('Table absente de la base de données.', 404, ['missing' => true]);
            }

            $rowCount = (int) Database::scalar("SELECT COUNT(*) FROM `$table`", [], 0);
            $taille   = (int) ($statut['taille'] ?? 0);
            $columns  = Database::pdo()->query("DESCRIBE `$table`")->fetchAll(\PDO::FETCH_ASSOC);

            $foreignKeys = Database::all(
                'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                   FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :t
                    AND REFERENCED_TABLE_NAME IS NOT NULL',
                ['t' => $table]
            );
        } catch (\Throwable $e) {
            Logger::error('Erreur lors de la récupération des informations de la table ' . $table, $e);
            $this->ko('Erreur lors de la récupération des informations de la table.', 500);
        }

        $this->ok('Informations de la table ' . $table, [
            'table'       => $table,
            'rowCount'    => $rowCount,
            'sizeBytes'   => $taille,
            'sizeMB'      => round($taille / (1024 * 1024), 2),
            'columns'     => $columns,
            'foreignKeys' => $foreignKeys,
        ]);
    }

    /** Effacer une table (avec confirmation). */
    public function clearTable(): void
    {
        $this->guardPost(Auth::ROLE_ADMIN);

        $table = trim((string) Request::input('table', ''));
        $confirmation = (string) Request::input('confirmation', '');

        if ($table === '') {
            Flash::add('warning', 'Table non spécifiée.');
            $this->redirect('/admin/parametres/base');
        }

        if ($confirmation !== 'SUPPRIMER') {
            Flash::add('warning', 'Veuillez confirmer l\'operation en saisissant "SUPPRIMER".');
            $this->redirect('/admin/parametres/base');
        }

        if (!in_array($table, self::TABLES_BASE, true)) {
            Flash::add('danger', 'Table non autorisée.');
            $this->redirect('/admin/parametres/base');
        }

        try {
            $pdo = Database::pdo();

            // TRUNCATE ne renvoie pas de nombre de lignes : on le relève avant.
            $lignes = (int) Database::scalar("SELECT COUNT(*) FROM `$table`", [], 0);

            // Désactiver la vérification des clés étrangères temporairement
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            try {
                // Supprimer les données
                $pdo->exec("TRUNCATE TABLE `$table`");
            } finally {
                // Réactiver la vérification des clés étrangères
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }

            Flash::add('success', "Table '$table' vidée avec succès. $lignes lignes supprimées.");
        } catch (\Throwable $e) {
            Logger::error('Erreur lors de la vidange de la table ' . $table, $e);
            Flash::add('danger', 'Erreur lors de la vidange de la table : ' . $e->getMessage());
        }

        $this->redirect('/admin/parametres/base');
    }

    /** @return array<string, string> */
    private function valeurs(): array
    {
        $valeurs = self::DEFAUTS;
        foreach (Database::all('SELECT cle, valeur FROM parametres') as $ligne) {
            $valeurs[(string) $ligne['cle']] = (string) $ligne['valeur'];
        }

        return $valeurs;
    }

    private function valeur(string $cle, string $defaut): string
    {
        return (string) Mailer::param($cle, $defaut);
    }

    private function ecrire(string $cle, string $valeur): void
    {
        Database::run(
            'INSERT INTO parametres (cle, valeur) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valeur = :v2',
            ['c' => $cle, 'v' => $valeur, 'v2' => $valeur]
        );
    }
}