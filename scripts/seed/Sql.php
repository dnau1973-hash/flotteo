<?php
declare(strict_types=1);

namespace Seed;

/**
 * Génération du fichier SQL statique `sql/demodata.sql`.
 *
 * Le fichier produit est exécutable d'un seul bloc :
 *
 *     mysql -u <utilisateur> -p <base> < sql/demodata.sql
 *
 * Il reprend les mêmes données que l'injecteur PHP — `Data` est l'unique source
 * de vérité — avec désactivation puis réactivation des clés étrangères autour de
 * la purge, et les condensats générés à la volée par `password_hash()`.
 */
final class Sql
{
    /** @return string contenu complet du fichier SQL */
    public static function generer(): string
    {
        $lignes = [];

        foreach (self::entete() as $ligne) {
            $lignes[] = $ligne;
        }
        $lignes[] = '';

        foreach (self::corps() as $section) {
            foreach ($section as $ligne) {
                $lignes[] = $ligne;
            }
        }

        return implode(PHP_EOL, $lignes) . PHP_EOL;
    }

    /** @return list<string> */
    private static function entete(): array
    {
        return [
            '-- =============================================================================',
            '-- Flotteo - Jeu de données de démonstration',
            '-- FICHIER GÉNÉRÉ : ne pas éditer à la main.',
            '-- Source : scripts/seed/Data.php   Générateur : php scripts/seed_demo.php --sql',
            '--',
            '-- Usage : mysql -u <utilisateur> -p <base> < sql/demodata.sql',
            '--',
            '-- ATTENTION : ce jeu écrase les données existantes et installe des mots de',
            '-- passe connus. Il est réservé au développement et à la recette.',
            '--',
            '-- Comptes de démonstration, mot de passe commun pour les trois :',
            sprintf('--     %-26s %s', 'Password123!', '(à changer impérativement hors développement)'),
            '--   admin@flotteo.local        rôle administration',
            '--   gestion@flotteo.local      rôle modification',
            '--   consultation@flotteo.local  rôle lecture_seule',
            '--',
            '-- Les clés étrangères sont désactivées pendant la purge puis réactivées :',
            '-- TRUNCATE réinitialise aussi les compteurs AUTO_INCREMENT.',
            '-- =============================================================================',
            '',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS = 0;',
            '',
            'TRUNCATE TABLE incidents_fichiers;',
            'TRUNCATE TABLE incidents;',
            'TRUNCATE TABLE maintenances;',
            'TRUNCATE TABLE vehicules;',
            'TRUNCATE TABLE types_intervention;',
            'TRUNCATE TABLE lieux;',
            'TRUNCATE TABLE loueurs;',
            'TRUNCATE TABLE modeles;',
            'TRUNCATE TABLE marques;',
            'TRUNCATE TABLE entites;',
            'TRUNCATE TABLE utilisateurs;',
            'TRUNCATE TABLE parametres;',
            '',
            'SET FOREIGN_KEY_CHECKS = 1;',
            '',
        ];
    }

    /** @return list<list<string>> une section par jeu de données */
    private static function corps(): array
    {
        return [
            self::sectionUtilisateurs(),
            self::sectionReferentiels(),
            self::sectionParametres(),
            self::sectionVehicules(),
            self::sectionMaintenances(),
            self::sectionIncidents(),
            self::sectionFichiers(),
        ];
    }

    /** @return list<string> */
    private static function sectionUtilisateurs(): array
    {
        $hash = password_hash(MdpDemo::valeur(), MdpDemo::algorithme());

        $lignes = [
            '-- --- Utilisateurs de démonstration -----------------------------------------',
            sprintf('-- Mot de passe en clair : %s', MdpDemo::valeur()),
            sprintf('-- Algorithme : %s (password_hash)', MdpDemo::libelle()),
            '--',
        ];

        foreach (Data::utilisateurs() as $u) {
            $lignes[] = sprintf(
                "INSERT INTO utilisateurs (id, nom, email, password_hash, role, actif) VALUES (%d, %s, %s, %s, %s, %d);",
                $u['id'],
                self::chaine($u['nom']),
                self::chaine($u['email']),
                self::chaine($hash),
                self::chaine($u['role']),
                $u['actif']
            );
        }

        return array_merge($lignes, ['']);
    }

    /** @return list<string> */
    private static function sectionReferentiels(): array
    {
        $lignes = [
            '-- --- Marques et modèles ------------------------------------------------',
            'INSERT INTO marques (id, nom) VALUES',
        ];
        $lignes = array_merge($lignes, self::lignesValeurs(Data::marques(), ['id', 'nom']));

        $lignes[] = '';
        $lignes[] = 'INSERT INTO modeles (id, marque_id, nom) VALUES';
        $lignes = array_merge($lignes, self::lignesValeurs(Data::modeles(), ['id', 'marque_id', 'nom']));

        $lignes[] = '';
        $lignes[] = 'INSERT INTO entites (id, nom, code) VALUES';
        $lignes = array_merge($lignes, self::lignesValeurs(Data::entites(), ['id', 'nom', 'code']));

        $lignes[] = '';
        $lignes[] = 'INSERT INTO loueurs (id, nom, contact_email, telephone) VALUES';
        $lignes = array_merge($lignes, self::lignesValeurs(Data::loueurs(), ['id', 'nom', 'contact_email', 'telephone']));

        $lignes[] = '';
        $lignes[] = 'INSERT INTO lieux (id, nom, ville) VALUES';
        $lignes = array_merge(
            $lignes,
            self::lignesValeurs(
                array_map(
                    static fn (array $l): array => ['id' => $l['id'], 'nom' => $l['nom'], 'ville' => $l['ville']],
                    Data::lieux()
                ),
                ['id', 'nom', 'ville']
            )
        );

        $lignes[] = '';
        $lignes[] = 'INSERT INTO types_intervention (id, libelle, categorie, actif) VALUES';
        $lignes = array_merge(
            $lignes,
            // `actif` doit figurer dans la liste des colonnes : lignesValeurs()
            // n'itere que sur elle, la valeur constante vient de $constantes.
            self::lignesValeurs(
                Data::typesIntervention(),
                ['id', 'libelle', 'categorie', 'actif'],
                ['actif' => 1]
            )
        );

        return array_merge($lignes, ['']);
    }

    /** @return list<string> */
    private static function sectionParametres(): array
    {
        $lignes = [
            '-- --- Paramétrage système ------------------------------------------------',
            'INSERT INTO parametres (cle, valeur) VALUES',
        ];

        return array_merge($lignes, self::lignesValeurs(Data::parametres(), ['cle', 'valeur']), ['']);
    }

    /** @return list<string> */
    private static function sectionVehicules(): array
    {
        $lignes = [
            '-- --- Parc de véhicules (15) ---------------------------------------------',
            '-- date_sortie_prevue est calculée à l\'import : les paliers d\'alerte',
            sprintf('-- 90 / 180 / 270 jours (parametres.alerte_palier_1..3) restent atteints.'),
            '-- Le dernier groupe de l\'immatriculation est le code département (2A = Corse).',
            'INSERT INTO vehicules',
            '    (id, immatriculation, modele_id, entite_id, loueur_id, lieu_id,',
            '     date_entree, date_sortie_prevue, date_sortie_effective, statut, immatricule, commentaire)',
            'VALUES',
        ];

        $rangees = [];
        foreach (Data::vehicules() as $v) {
            $effective = $v['sortie_effective'] === null
                ? 'NULL'
                : self::chaine(Calendrier::jour((int) $v['sortie_effective']));

            $rangees[] = sprintf(
                '    (%d, %s, %d, %d, %d, %d, %s, %s, %s, %s, %d, %s)',
                $v['id'],
                self::chaine((string) $v['immat']),
                $v['modele_id'],
                $v['entite_id'],
                $v['loueur_id'],
                $v['lieu_id'],
                self::chaine((string) $v['entree']),
                self::chaine(Calendrier::jour((int) $v['jours'])),
                $effective,
                self::chaine((string) $v['statut']),
                (int) $v['immatricule'],
                self::chaine((string) $v['commentaire'])
            );
        }

        $lignes[] = implode(",\n", $rangees) . ';';

        return array_merge($lignes, ['']);
    }

    /** @return list<string> */
    private static function sectionMaintenances(): array
    {
        $lignes = [
            '-- --- Historique d\'entretien (22 opérations) ------------------------------',
            'INSERT INTO maintenances',
            '    (vehicule_id, type_intervention_id, date_operation, kilometrage, cout_ht, cout_ttc, commentaire)',
            'VALUES',
        ];

        $rangees = [];
        foreach (Data::maintenances() as $m) {
            $ht = round((float) $m['ht'], 2);
            $rangees[] = sprintf(
                '    (%d, %d, %s, %d, %.2f, %.2f, %s)',
                $m['vehicule_id'],
                $m['type_id'],
                self::chaine(Calendrier::jour((int) $m['jours'])),
                $m['km'],
                $ht,
                round($ht * Data::TVA, 2),
                self::chaine((string) $m['motif'])
            );
        }

        $lignes[] = implode(",\n", $rangees) . ';';

        return array_merge($lignes, ['']);
    }

    /** @return list<string> */
    private static function sectionIncidents(): array
    {
        $lignes = [
            '-- --- Incidents et sinistres (7) ------------------------------------------',
            'INSERT INTO incidents',
            '    (id, vehicule_id, type, date_incident, lieu, responsable, immatricule, statut, description)',
            'VALUES',
        ];

        $rangees = [];
        foreach (Data::incidents() as $i) {
            $rangees[] = sprintf(
                '    (%d, %d, %s, %s, %s, %d, %d, %s, %s)',
                $i['id'],
                $i['vehicule_id'],
                self::chaine((string) $i['type']),
                self::chaine(Calendrier::jour((int) $i['jours'])),
                self::chaine((string) $i['lieu']),
                (int) $i['responsable'],
                (int) $i['immatricule'],
                self::chaine((string) $i['statut']),
                self::chaine((string) $i['description'])
            );
        }

        $lignes[] = implode(",\n", $rangees) . ';';

        return array_merge($lignes, ['']);
    }

    /** @return list<string> */
    private static function sectionFichiers(): array
    {
        $lignes = [
            '-- --- Pièces jointes (14) ------------------------------------------------',
            '-- Les fichiers eux-mêmes ne sont pas fournis : seules leurs métadonnées',
            '-- sont enregistrées, conformément au modèle de données.',
            'INSERT INTO incidents_fichiers',
            '    (id, incident_id, nom_fichier, nom_original, mime, taille)',
            'VALUES',
        ];

        $rangees = [];
        foreach (Data::fichiers() as $f) {
            $rangees[] = sprintf(
                '    (%d, %d, %s, %s, %s, %d)',
                $f['id'],
                $f['incident_id'],
                self::chaine((string) $f['fichier']),
                self::chaine((string) $f['original']),
                self::chaine((string) $f['mime']),
                (int) $f['ko'] * 1024
            );
        }

        $lignes[] = implode(",\n", $rangees) . ';';

        return array_merge($lignes, ['']);
    }

    /**
     * Assemble un bloc INSERT ... VALUES à partir d'un jeu de lignes.
     * La dernière ligne ne porte pas de virgule.
     *
     * @param list<array<string, mixed>> $lignes
     * @param list<string> $colonnes
     * @param array<string, mixed> $constantes colonnes à valeur fixe
     * @return list<string>
     */
    private static function lignesValeurs(array $lignes, array $colonnes, array $constantes = []): array
    {
        // Garde-fou : une constante pour une colonne absente de $colonnes serait
        // ignoree en silence, et le SQL produit aurait un nombre de colonnes
        // different du nombre de valeurs — rejet par le serveur, apres les
        // TRUNCATE. Better vaut echouer ici, avant toute ecriture.
        $orphelines = array_diff(array_keys($constantes), $colonnes);
        if ($orphelines !== []) {
            throw new \LogicException(sprintf(
                'Constante fournie pour une colonne absente de la liste : %s. Colonnes attendues : %s.',
                implode(', ', $orphelines),
                implode(', ', $colonnes)
            ));
        }

        $corps = [];
        $total = count($lignes);

        foreach ($lignes as $index => $ligne) {
            $valeurs = [];
            foreach ($colonnes as $colonne) {
                $valeur = $constantes[$colonne] ?? $ligne[$colonne];
                $valeurs[] = is_int($valeur) ? (string) $valeur : self::chaine((string) $valeur);
            }

            $corps[] = '    (' . implode(', ', $valeurs) . ')' . ($index === $total - 1 ? ';' : ',');
        }

        return $corps;
    }

    /**
     * Échappement SQL.
     *
     * Les apostrophes sont doublées plutôt que préfixées d'une barre oblique :
     * c'est la forme normalisée SQL, qui reste correcte même si le serveur
     * fonctionne en mode NO_BACKSLASH_ESCAPES. Les barres obliques sont
     * doublées en conséquence pour ne pas devenir un échappement.
     *
     * Les valeurs proviennent des fixtures et jamais d'une saisie utilisateur ;
     * l'échappement reste appliqué par prudence.
     */
    private static function chaine(string $valeur): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "''"], $valeur) . "'";
    }
}