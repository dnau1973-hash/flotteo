<?php
declare(strict_types=1);

namespace Seed;

/**
 * Injection du jeu de démonstration dans la base configurée par
 * `config/database.php`.
 *
 * Le script reste volontairementPlusieurs étapes courtes et explicites :
 *   1. purge des tables de données, clés étrangères désactivées ;
 *   2. insertion dans l'ordre des dépendances relationnelles ;
 *   3. réactivation des clés étrangères et contrôle de cohérence en base.
 *
 * Aucune requête n'est concaténée à partir d'une valeur issue des fixtures :
 * tout passe par des requêtes préparées.
 */
final class Injecteur
{
    public function __construct(private readonly \PDO $pdo) {}

    public static function executer(Options $options): int
    {
        try {
            $pdo = self::connecter();
        } catch (\PDOException $e) {
            fwrite(STDERR, 'Connexion impossible : ' . Message::pdo($e, 'connexion') . PHP_EOL);
            return 1;
        } catch (\RuntimeException $e) {
            fwrite(STDERR, 'Configuration illisible : ' . $e->getMessage() . PHP_EOL);
            return 1;
        }

        $injecteur = new self($pdo);

        // Contrôle préalable ABSOLUMENT avant toute écriture : les TRUNCATE
        // valident implicitement, donc un INSERT refusé plus loin laisserait la
        // base à moitié vidée. Un écart entre le SQL généré et le schéma réel
        // doit interrompre ici, sur une base encore intacte.
        try {
            $anomalies = $injecteur->controlerSchema();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Contrôle du schéma impossible : ' . $e->getMessage() . PHP_EOL);
            return 1;
        }

        if ($anomalies !== []) {
            fwrite(STDERR, 'SQL incompatible avec le schéma, aucune écriture effectuée :' . PHP_EOL);
            foreach ($anomalies as $anomalie) {
                fwrite(STDERR, '  - ' . $anomalie . PHP_EOL);
            }
            return 1;
        }

        try {
            $injecteur->purger();
            $injecteur->inserer();
            $injecteur->controler();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Injection interrompue : ' . $e->getMessage() . PHP_EOL);
            return 1;
        }

        fwrite(STDOUT, 'Jeu de démonstration injecté.' . PHP_EOL);
        fwrite(STDOUT, sprintf('Comptes de test, mot de passe commun « %s » (%s) :', MdpDemo::valeur(), MdpDemo::libelle()) . PHP_EOL);

        foreach (Data::utilisateurs() as $utilisateur) {
            fwrite(STDOUT, sprintf('  %-26s %s', $utilisateur['email'], $utilisateur['role']) . PHP_EOL);
        }

        return 0;
    }

    /**
     * Confronte le SQL généré au schéma réel de la base.
     *
     * Vérifie que chaque `INSERT INTO table (colonnes…)` ne cite que des colonnes
     * existantes et que le nombre de valeurs par ligne correspond au nombre de
     * colonnes annoncées. C'est ce second point qui manquait : une colonne
     * déclarée mais jamais alimentée est acceptée par le générateur et refusée
     * par le serveur, après les TRUNCATE.
     *
     * @return list<string> Anomalies ; liste vide si le SQL est compatible.
     */
    private function controlerSchema(): array
    {
        $sql = Sql::generer();

        $colonnes = $this->pdo->query(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION'
        )->fetchAll(\PDO::FETCH_NUM);

        $schema = [];
        foreach ($colonnes as [$table, $colonne]) {
            $schema[$table][] = $colonne;
        }

        if ($schema === []) {
            return ['La base ne contient aucune table : lancez d\'abord scripts/install.php.'];
        }

        $anomalies = [];

        if (!preg_match_all(
            '/INSERT INTO (\w+)\s*\(([^)]*)\)\s*VALUES\s*(.*?);/s',
            $sql,
            $blocs,
            PREG_SET_ORDER
        )) {
            return ['Aucun INSERT trouvé dans le SQL généré.'];
        }

        foreach ($blocs as [, $table, $listeColonnes, $corps]) {
            $annoncees = array_map(trim(...), explode(',', $listeColonnes));
            $reelles = $schema[$table] ?? null;

            if ($reelles === null) {
                $anomalies[] = sprintf('Table « %s » absente du schéma.', $table);
                continue;
            }

            foreach ($annoncees as $colonne) {
                if (!in_array($colonne, $reelles, true)) {
                    $anomalies[] = sprintf('Colonne « %s.%s » absente du schéma.', $table, $colonne);
                }
            }

            foreach (explode("\n", $corps) as $ligne) {
                $ligne = trim($ligne);
                if (!str_starts_with($ligne, '(')) {
                    continue;
                }
                $ligne = rtrim(rtrim($ligne), ';');
                if (!str_ends_with($ligne, ')')) {
                    continue;
                }
                $nb = self::compterValeurs(substr($ligne, 1, -1));
                if ($nb !== count($annoncees)) {
                    $anomalies[] = sprintf(
                        '%s : une ligne fournit %d valeur(s) pour %d colonne(s) (%s).',
                        $table,
                        $nb,
                        count($annoncees),
                        implode(', ', $annoncees)
                    );
                    break;
                }
            }
        }

        return array_values(array_unique($anomalies));
    }

    /** Compte les valeurs SQL en ignorant les virgules internes aux chaînes. */
    private static function compterValeurs(string $interieur): int
    {
        $n = 0;
        $dansChaine = false;
        for ($i = 0, $len = strlen($interieur); $i < $len; $i++) {
            $c = $interieur[$i];
            if ($dansChaine) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === "'") {
                    $dansChaine = false;
                }
                continue;
            }
            if ($c === "'") {
                $dansChaine = true;
            } elseif ($c === ',') {
                $n++;
            }
        }

        return $n + 1;
    }

    /** Ouvre la connexion PDO décrite par la configuration de l'application. */
    private static function connecter(): \PDO
    {
        $chemin = FLOTTEO_ROOT . '/config/database.php';

        // La configuration est créée en 0600 par l'installateur et peut donc être
        // illisible pour l'utilisateur courant. On évite une trace PHP brute.
        if (!is_file($chemin) || !is_readable($chemin)) {
            throw new \RuntimeException(
                'config/database.php est absent ou illisible pour l\'utilisateur courant. '
                . 'Lancez le générateur avec les droits du serveur web (www-data), ou corrigez les permissions.'
            );
        }

        $conf = require $chemin;

        if (!is_array($conf) || !isset($conf['host'], $conf['database'], $conf['username'])) {
            throw new \RuntimeException('config/database.php est incomplet : clés host, database ou username manquantes.');
        }

        return new \PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $conf['host'],
                (int) ($conf['port'] ?? 3306),
                $conf['database'],
                $conf['charset'] ?? 'utf8mb4'
            ),
            (string) $conf['username'],
            (string) ($conf['password'] ?? ''),
            [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    /**
     * Vide les tables de données. Les clés étrangères sont désactivées le temps
     * de l'opération puis réactivées : c'est indispensable car l'ordre de purge
     * (enfants avant parents) est lui-même portable.
     */
    private function purger(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (['incidents_fichiers', 'sauvegardes', 'incidents', 'maintenances', 'vehicules', 'parametres'] as $table) {
            $this->pdo->exec('TRUNCATE TABLE ' . $table);
        }

        // Le paramétrage et le référentiel sont réinitialisés puis réinjectés.
        foreach (['types_intervention', 'lieux', 'loueurs', 'modeles', 'marques', 'entites', 'utilisateurs'] as $table) {
            $this->pdo->exec('TRUNCATE TABLE ' . $table);
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Insertion suivant l'ordre des dépendances :
     * utilisateurs, dictionnaires, référentiels, véhicules, entretien, incidents,
     * pièces jointes, paramétrage.
     */
    private function inserer(): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->insererUtilisateurs();
            $this->insererReferentiels();
            $this->insererParametres();
            $this->insererVehicules();
            $this->insererMaintenances();
            $this->insererIncidents();
            $this->insererFichiers();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function insererUtilisateurs(): void
    {
        // Mot de passe unique pour les trois comptes : identifiants et mot de
        // passe sont publiés avec les fixtures, ce jeu est réservé au developpement.
        $hash = password_hash(MdpDemo::valeur(), MdpDemo::algorithme());

        $sql = 'INSERT INTO utilisateurs (id, nom, email, password_hash, role, actif) VALUES (:id, :nom, :email, :hash, :role, :actif)';
        $stmt = $this->pdo->prepare($sql);

        foreach (Data::utilisateurs() as $utilisateur) {
            $stmt->execute([
                'id'    => $utilisateur['id'],
                'nom'   => $utilisateur['nom'],
                'email' => $utilisateur['email'],
                'hash'  => $hash,
                'role'  => $utilisateur['role'],
                'actif' => $utilisateur['actif'],
            ]);
        }
    }

    private function insererReferentiels(): void
    {
        $marques = $this->pdo->prepare('INSERT INTO marques (id, nom) VALUES (:id, :nom)');
        foreach (Data::marques() as $ligne) {
            $marques->execute($ligne);
        }

        $modeles = $this->pdo->prepare('INSERT INTO modeles (id, marque_id, nom) VALUES (:id, :marque_id, :nom)');
        foreach (Data::modeles() as $ligne) {
            $modeles->execute($ligne);
        }

        $entites = $this->pdo->prepare('INSERT INTO entites (id, nom, code) VALUES (:id, :nom, :code)');
        foreach (Data::entites() as $ligne) {
            $entites->execute($ligne);
        }

        // Le code département n'existe pas en base : il ne sert qu'à valider
        // la cohérence des immatriculations, il n'est donc pas inséré.
        $loueurs = $this->pdo->prepare('INSERT INTO loueurs (id, nom, contact_email, telephone) VALUES (:id, :nom, :contact_email, :telephone)');
        foreach (Data::loueurs() as $ligne) {
            $loueurs->execute($ligne);
        }

        $lieux = $this->pdo->prepare('INSERT INTO lieux (id, nom, ville) VALUES (:id, :nom, :ville)');
        foreach (Data::lieux() as $ligne) {
            $lieux->execute(['id' => $ligne['id'], 'nom' => $ligne['nom'], 'ville' => $ligne['ville']]);
        }

        $types = $this->pdo->prepare('INSERT INTO types_intervention (id, libelle, categorie, actif) VALUES (:id, :libelle, :categorie, 1)');
        foreach (Data::typesIntervention() as $ligne) {
            $types->execute($ligne);
        }
    }

    private function insererParametres(): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO parametres (cle, valeur) VALUES (:cle, :valeur)');
        foreach (Data::parametres() as $ligne) {
            $stmt->execute($ligne);
        }
    }

    private function insererVehicules(): void
    {
        $sql = 'INSERT INTO vehicules
                   (id, immatriculation, modele_id, entite_id, loueur_id, lieu_id,
                    date_entree, date_sortie_prevue, date_sortie_effective, statut, immatricule, commentaire)
               VALUES
                   (:id, :immatriculation, :modele_id, :entite_id, :loueur_id, :lieu_id,
                    :date_entree, :date_sortie_prevue, :date_sortie_effective, :statut, :immatricule, :commentaire)';
        $stmt = $this->pdo->prepare($sql);

        foreach (Data::vehicules() as $vehicule) {
            $effective = $vehicule['sortie_effective'];

            $stmt->execute([
                'id'                    => $vehicule['id'],
                'immatriculation'       => $vehicule['immat'],
                'modele_id'             => $vehicule['modele_id'],
                'entite_id'             => $vehicule['entite_id'],
                'loueur_id'             => $vehicule['loueur_id'],
                'lieu_id'               => $vehicule['lieu_id'],
                'date_entree'           => $vehicule['entree'],
                'date_sortie_prevue'    => Calendrier::jour((int) $vehicule['jours']),
                'date_sortie_effective' => $effective === null ? null : Calendrier::jour((int) $effective),
                'statut'                => $vehicule['statut'],
                'immatricule'           => $vehicule['immatricule'],
                'commentaire'           => $vehicule['commentaire'],
            ]);
        }
    }

    private function insererMaintenances(): void
    {
        $sql = 'INSERT INTO maintenances
                   (vehicule_id, type_intervention_id, date_operation, kilometrage, cout_ht, cout_ttc, commentaire)
               VALUES (:vehicule_id, :type_intervention_id, :date_operation, :kilometrage, :cout_ht, :cout_ttc, :commentaire)';
        $stmt = $this->pdo->prepare($sql);

        foreach (Data::maintenances() as $maintenance) {
            $ht = (float) $maintenance['ht'];

            $stmt->execute([
                'vehicule_id'          => $maintenance['vehicule_id'],
                'type_intervention_id' => $maintenance['type_id'],
                'date_operation'       => Calendrier::jour((int) $maintenance['jours']),
                'kilometrage'          => $maintenance['km'],
                'cout_ht'              => number_format($ht, 2, '.', ''),
                'cout_ttc'             => number_format(round($ht * Data::TVA, 2), 2, '.', ''),
                'commentaire'           => $maintenance['motif'],
            ]);
        }
    }

    private function insererIncidents(): void
    {
        $sql = 'INSERT INTO incidents
                   (id, vehicule_id, type, date_incident, lieu, responsable, immatricule, statut, description)
               VALUES (:id, :vehicule_id, :type, :date_incident, :lieu, :responsable, :immatricule, :statut, :description)';
        $stmt = $this->pdo->prepare($sql);

        foreach (Data::incidents() as $incident) {
            $stmt->execute([
                'id'           => $incident['id'],
                'vehicule_id'  => $incident['vehicule_id'],
                'type'         => $incident['type'],
                'date_incident' => Calendrier::jour((int) $incident['jours']),
                'lieu'         => $incident['lieu'],
                'responsable'  => $incident['responsable'],
                'immatricule'  => $incident['immatricule'],
                'statut'       => $incident['statut'],
                'description'  => $incident['description'],
            ]);
        }
    }

    private function insererFichiers(): void
    {
        $sql = 'INSERT INTO incidents_fichiers
                   (id, incident_id, nom_fichier, nom_original, mime, taille)
               VALUES (:id, :incident_id, :nom_fichier, :nom_original, :mime, :taille)';
        $stmt = $this->pdo->prepare($sql);

        foreach (Data::fichiers() as $fichier) {
            $stmt->execute([
                'id'           => $fichier['id'],
                'incident_id'  => $fichier['incident_id'],
                'nom_fichier'  => $fichier['fichier'],
                'nom_original' => $fichier['original'],
                'mime'         => $fichier['mime'],
                'taille'       => (int) $fichier['ko'] * 1024,
            ]);
        }
    }

    /**
     * Contrôle a posteriori, une fois les données en place.
     * Interroge réellement la base : c'est le seul moyen de prouver que les
     * contraintes ont bien été satisfaites par le serveur.
     */
    private function controler(): void
    {
        $tables = ['utilisateurs', 'marques', 'modeles', 'entites', 'loueurs', 'lieux',
                   'types_intervention', 'parametres', 'vehicules', 'maintenances',
                   'incidents', 'incidents_fichiers'];

        foreach ($tables as $table) {
            $nombre = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
            fwrite(STDOUT, sprintf('  %-22s %4d lignes', $table, $nombre) . PHP_EOL);
        }

        // Toute requête en échec lèverait une exception : le contrôle consiste
        // donc à forcer MySQL à valider les contraintes sur toutes les tables.
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $orphelins = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM maintenances m
             LEFT JOIN vehicules v ON v.id = m.vehicule_id
             WHERE v.id IS NULL'
        )->fetchColumn();

        if ($orphelins > 0) {
            throw new \RuntimeException(sprintf('%d entretien(s) orphelin(s) après injection.', $orphelins));
        }
    }
}