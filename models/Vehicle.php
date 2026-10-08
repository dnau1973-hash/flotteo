<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;
use Core\Request;

/**
 * Accès au parc de véhicules et à ses jointures de nomenclature.
 */
final class Vehicle
{
    public const STATUTS = [
        'actif'       => 'Actif',
        'immobilise'  => 'Immobilisé',
        'sorti'       => 'Sorti de flotte',
    ];

    public const MOTORISATIONS = [
        'essence'    => 'Essence',
        'diesel'     => 'Diesel',
        'electrique' => 'Électrique',
        'hybride'    => 'Hybride',
    ];

    public const TYPES_BOITE = [
        'mecanique'   => 'Mécanique',
        'automatique' => 'Automatique',
    ];

    public const SELECT_BASE = '
        SELECT v.*,
               mo.nom AS modele_nom, ma.nom AS marque_nom, ma.logo AS marque_logo,
               e.nom AS entite_nom, e.code AS entite_code,
               l.nom AS loueur_nom, li.nom AS lieu_nom, li.ville AS lieu_ville
        FROM vehicules v
        INNER JOIN modeles mo ON mo.id = v.modele_id
        INNER JOIN marques  ma ON ma.id = mo.marque_id
        INNER JOIN entites  e  ON e.id  = v.entite_id
        INNER JOIN loueurs  l  ON l.id  = v.loueur_id
        INNER JOIN lieux    li ON li.id = v.lieu_id';

    public static function find(int $id): ?array
    {
        $v = Database::one(self::SELECT_BASE . ' WHERE v.id = :id', ['id' => $id]);
        if ($v !== null) {
            try {
                $v['marque_logo'] = Database::scalar(
                    'SELECT ma.logo FROM marques ma
                     JOIN modeles mo ON mo.marque_id = ma.id
                     WHERE mo.id = :modele_id',
                    ['modele_id' => $v['modele_id']]
                );
            } catch (\PDOException $e) { \Core\Logger::error($e->getMessage()); 
                $v['marque_logo'] = null;
            }
            try {
                $v['modele_photo'] = Database::scalar(
                    'SELECT photo FROM modeles WHERE id = :modele_id',
                    ['modele_id' => $v['modele_id']]
                );
            } catch (\PDOException $e) { \Core\Logger::error($e->getMessage()); 
                $v['modele_photo'] = null;
            }
        }
        return $v;
    }

    /**
     * Prépare les clauses WHERE et les paramètres pour le filtrage du parc.
     *
     * @param array{q?:string, entite?:int, loueur?:int, lieu?:int, statut?:string, echeance?:string} $filtres
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private static function buildWhereFiltres(array $filtres): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($filtres['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(v.immatriculation LIKE :q1 OR mo.nom LIKE :q2 OR ma.nom LIKE :q3)';
            $terme = '%' . $q . '%';
            $params['q1'] = $terme;
            $params['q2'] = $terme;
            $params['q3'] = $terme;
        }
        foreach (['entite' => 'v.entite_id', 'loueur' => 'v.loueur_id', 'lieu' => 'v.lieu_id'] as $cle => $col) {
            if (!empty($filtres[$cle])) {
                $where[] = "$col = :$cle";
                $params[$cle] = (int) $filtres[$cle];
            }
        }
        if (!empty($filtres['statut'])) {
            $where[] = 'v.statut = :statut';
            $params['statut'] = (string) $filtres['statut'];
        }
        if (($filtres['echeance'] ?? '') === '90') {
            $where[] = 'v.date_sortie_effective IS NULL AND v.date_sortie_prevue <= :echeance';
            $params['echeance'] = date('Y-m-d', strtotime('+90 days'));
        }

        return [$where, $params];
    }

    /**
     * Nombre total de véhicules correspondant aux filtres.
     *
     * @param array{q?:string, entite?:int, loueur?:int, lieu?:int, statut?:string, echeance?:string} $filtres
     */
    public static function count(array $filtres = []): int
    {
        [$where, $params] = self::buildWhereFiltres($filtres);

        $sql = 'SELECT COUNT(*)
                FROM vehicules v
                INNER JOIN modeles mo ON mo.id = v.modele_id
                INNER JOIN marques  ma ON ma.id = mo.marque_id
                INNER JOIN entites  e  ON e.id  = v.entite_id
                INNER JOIN loueurs  l  ON l.id  = v.loueur_id
                INNER JOIN lieux    li ON li.id = v.lieu_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        try {
            return (int) Database::scalar($sql, $params, 0);
        } catch (\PDOException $e) {
            Logger::error('Comptage du parc impossible', $e);
            return 0;
        }
    }

    /**
     * Liste filtrable du parc avec pagination optionnelle.
     *
     * @param array{q?:string, entite?:int, loueur?:int, lieu?:int, statut?:string, echeance?:string} $filtres
     */
    public static function search(array $filtres = [], ?int $limite = null, ?int $offset = null): array
    {
        [$where, $params] = self::buildWhereFiltres($filtres);

        $sql = self::SELECT_BASE;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY v.date_sortie_prevue ASC';

        if ($limite !== null && $limite > 0) {
            $sql .= sprintf(' LIMIT %d OFFSET %d', $limite, max(0, (int) $offset));
        }

        try {
            return Database::all($sql, $params);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'marque_logo') || str_contains($e->getMessage(), 'logo')) {
                $sqlFallback = str_replace('ma.logo AS marque_logo,', 'NULL AS marque_logo,', $sql);
                try {
                    return Database::all($sqlFallback, $params);
                } catch (\PDOException $e2) {
                    Logger::error('Lecture du parc impossible', $e2);
                    return [];
                }
            }
            Logger::error('Lecture du parc impossible', $e);
            return [];
        }
    }

    /**
     * Véhicules arrivant à échéance dans une plage de dates.
     *
     * `search()` ne sait borner que sur une échéance à 90 jours, calée sur les
     * paliers d'alerte. Un calendrier a besoin de la plage réellement affichée,
     * qui s'étend à d'autres mois selon la navigation : cette méthode prend donc
     * `du` et `au` au lieu d'un délai fixe.
     *
     * Seuls les véhicules non restitués sont retenus : une échéance déjà tenue
     * n'a plus lieu d'être signalée. L'ordre suit la date d'échéance, qui est
     * aussi l'ordre de lecture du calendrier.
     *
     * @return list<array<string, mixed>>
     */
    public static function echeancesEntre(string $du, string $au): array
    {
        $sql = self::SELECT_BASE . '
                 WHERE v.date_sortie_effective IS NULL
                   AND v.date_sortie_prevue BETWEEN :du AND :au
                 ORDER BY v.date_sortie_prevue ASC';

        try {
            return Database::all($sql, ['du' => $du, 'au' => $au]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'marque_logo') || str_contains($e->getMessage(), 'logo')) {
                $sqlFallback = str_replace('ma.logo AS marque_logo,', 'NULL AS marque_logo,', $sql);
                try {
                    return Database::all($sqlFallback, ['du' => $du, 'au' => $au]);
                } catch (\PDOException $e2) {
                    Logger::error('Lecture des échéances impossible', $e2);
                    return [];
                }
            }
            Logger::error('Lecture des échéances impossible', $e);
            return [];
        }
    }

    /** Filtres extraits de la requête courante. */
    public static function filtersFromRequest(): array
    {
        return [
            'q'        => (string) Request::input('q', ''),
            'entite'   => Request::int('entite'),
            'loueur'   => Request::int('loueur'),
            'lieu'     => Request::int('lieu'),
            'statut'   => (string) Request::input('statut', ''),
            'echeance' => (string) Request::input('echeance', ''),
        ];
    }

    /**
     * Liste des colonnes de la table `vehicules` issues du formulaire de saisie.
     * Seuls ces champs sont enregistrés lors de la création ou de la mise à jour.
     */
    public const CHAMPS_FORMULAIRE = [
        'immatriculation',
        'modele_id',
        'couleur',
        'motorisation',
        'type_boite',
        'entite_id',
        'loueur_id',
        'lieu_id',
        'statut',
        'date_entree',
        'duree_contrat',
        'km_maxi',
        'kilometrage',
        'sortie_angelus',
        'date_sortie_prevue',
        'hayon',
        'temps_controle_hayon',
        'date_dernier_controle_hayon',
        'date_prochain_controle_hayon',
        'commentaire',
    ];

    /** Construit un jeu de données validé à partir des champs du formulaire. */
    public static function buildPayload(): array
    {
        $statut = (string) Request::input('statut', 'actif');
        $couleur = trim((string) Request::input('couleur', ''));
        $motorisation = trim((string) Request::input('motorisation', ''));
        $typeBoite = trim((string) Request::input('type_boite', 'mecanique'));
        $hayon = Request::int('hayon') === 1 ? 1 : 0;
        $tempsHayon = Request::int('temps_controle_hayon');
        $dateDernier = Request::date('date_dernier_controle_hayon');
        $dateProchain = Request::date('date_prochain_controle_hayon');

        if ($hayon === 1) {
            // Calcul automatique si non renseigné explicitement mais que le dernier contrôle et la périodicité le permettent
            if ($dateProchain === '' && $dateDernier !== '' && $tempsHayon > 0) {
                $dateProchain = date('Y-m-d', (int) strtotime("+$tempsHayon months", strtotime($dateDernier)));
            }
        } else {
            $dateProchain = '';
        }

        $parseDate = static function (string $cle): ?string {
            $val = trim((string) Request::input($cle, ''));
            if ($val === '') {
                return null;
            }
            return self::parseDateCsv($val) ?: (Request::date($cle) ?: null);
        };

        return [
            'immatriculation'              => strtoupper(trim((string) Request::input('immatriculation', ''))),
            'modele_id'                    => Request::int('modele_id'),
            'couleur'                      => $couleur !== '' ? mb_substr($couleur, 0, 50) : null,
            'motorisation'                 => array_key_exists($motorisation, self::MOTORISATIONS) ? $motorisation : null,
            'type_boite'                   => array_key_exists($typeBoite, self::TYPES_BOITE) ? $typeBoite : 'mecanique',
            'entite_id'                    => Request::int('entite_id'),
            'loueur_id'                    => Request::int('loueur_id'),
            'lieu_id'                      => Request::int('lieu_id'),
            'statut'                       => array_key_exists($statut, self::STATUTS) ? $statut : 'actif',
            'date_entree'                  => (string) ($parseDate('date_entree') ?? ''),
            'duree_contrat'                => Request::int('duree_contrat') > 0 ? Request::int('duree_contrat') : null,
            'km_maxi'                      => Request::int('km_maxi') > 0 ? Request::int('km_maxi') : null,
            'kilometrage'                  => max(0, Request::int('kilometrage')),
            'sortie_angelus'               => $parseDate('sortie_angelus'),
            'date_sortie_prevue'           => (string) ($parseDate('date_sortie_prevue') ?? ''),
            'hayon'                        => $hayon,
            'temps_controle_hayon'         => $hayon === 1 && $tempsHayon > 0 ? $tempsHayon : null,
            'date_dernier_controle_hayon'  => $hayon === 1 && $dateDernier !== '' ? ($parseDate('date_dernier_controle_hayon') ?? $dateDernier) : null,
            'date_prochain_controle_hayon' => $hayon === 1 && $dateProchain !== '' ? ($parseDate('date_prochain_controle_hayon') ?? $dateProchain) : null,
            'commentaire'                  => mb_substr(trim((string) Request::input('commentaire', '')), 0, 2000),
        ];
    }

    public static function create(array $data): int
    {
        return Database::insert('vehicules', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::update('vehicules', $id, $data);
    }

    public static function delete(int $id): int
    {
        return Database::delete('vehicules', $id);
    }

    public static function findByImmatriculation(string $immat): ?array
    {
        return Database::one(
            'SELECT * FROM vehicules WHERE UPPER(TRIM(immatriculation)) = :immat LIMIT 1',
            ['immat' => strtoupper(trim($immat))]
        );
    }

    public static function immatriculationExists(string $immat, ?int $horsId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM vehicules WHERE immatriculation = :i';
        $params = ['i' => $immat];
        if ($horsId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $horsId;
        }
        return (int) Database::scalar($sql, $params, 0) > 0;
    }

    /**
     * Analyse et convertit une chaîne en date 'YYYY-MM-DD'.
     * Accepte YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY, YYYY/MM/DD.
     */
    public static function parseDateCsv(string $val): ?string
    {
        $val = trim($val);
        if ($val === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $val, $m)) {
            if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
            }
        }
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $val, $m)) {
            if (checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            }
        }
        if (preg_match('/^(\d{4})[\/\.](\d{1,2})[\/\.](\d{1,2})$/', $val, $m)) {
            if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
            }
        }
        $ts = strtotime($val);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }
        return null;
    }

    /**
     * Importe en masse des véhicules depuis un fichier CSV.
     * Colonnes attendues :
     * 1: immatriculation
     * 2: duree_contrat (mois)
     * 3: km_maxi
     * 4: date_entree (YYYY-MM-DD ou DD/MM/YYYY)
     *
     * @param string $cheminFichier Chemin temporaire du fichier CSV
     * @param array<string, int> $defaults Valeurs par défaut de nomenclature
     * @param string $mode 'upsert' | 'create_only' | 'update_only'
     * @return array{total: int, crees: int, modifies: int, ignores: int, erreurs: list<string>}
     */
    public static function importCsv(string $cheminFichier, array $defaults = [], string $mode = 'upsert'): array
    {
        $handle = fopen($cheminFichier, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier CSV.');
        }

        // Détection du délimiteur à partir de la première ligne
        $premiereLigne = fgets($handle);
        if ($premiereLigne === false) {
            fclose($handle);
            return ['total' => 0, 'crees' => 0, 'modifies' => 0, 'ignores' => 0, 'erreurs' => ['Le fichier CSV est vide.']];
        }

        // Nettoyage éventuel du BOM UTF-8
        $premiereLigne = preg_replace('/^\xEF\xBB\xBF/', '', $premiereLigne);

        $delims = [
            ';'  => substr_count($premiereLigne, ';'),
            ','  => substr_count($premiereLigne, ','),
            "\t" => substr_count($premiereLigne, "\t"),
        ];
        arsort($delims);
        $delim = key($delims);
        if ($delims[$delim] === 0) {
            $delim = ';';
        }

        rewind($handle);

        // Nomenclature par défaut pour les véhicules créés
        $modeleId = (int) ($defaults['modele_id'] ?? 0);
        if ($modeleId <= 0) {
            $modeleId = (int) Database::scalar('SELECT id FROM modeles ORDER BY id ASC LIMIT 1', [], 1);
        }
        $entiteId = (int) ($defaults['entite_id'] ?? 0);
        if ($entiteId <= 0) {
            $entiteId = (int) Database::scalar('SELECT id FROM entites ORDER BY id ASC LIMIT 1', [], 1);
        }
        $loueurId = (int) ($defaults['loueur_id'] ?? 0);
        if ($loueurId <= 0) {
            $loueurId = (int) Database::scalar('SELECT id FROM loueurs ORDER BY id ASC LIMIT 1', [], 1);
        }
        $lieuId = (int) ($defaults['lieu_id'] ?? 0);
        if ($lieuId <= 0) {
            $lieuId = (int) Database::scalar('SELECT id FROM lieux ORDER BY id ASC LIMIT 1', [], 1);
        }

        $stats = [
            'total'    => 0,
            'crees'    => 0,
            'modifies' => 0,
            'ignores'  => 0,
            'erreurs'  => [],
        ];

        $numLigne = 0;
        while (($row = fgetcsv($handle, 4096, $delim)) !== false) {
            $numLigne++;

            if ($row === [null] || $row === false || (count($row) === 1 && trim((string)$row[0]) === '')) {
                continue;
            }

            if ($numLigne === 1 && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }

            // Détection et saut de la ligne d'en-tête
            if ($numLigne === 1) {
                $texte1 = mb_strtolower(implode(' ', array_map('trim', $row)));
                if (str_contains($texte1, 'immat') || str_contains($texte1, 'duree') || str_contains($texte1, 'contrat') || str_contains($texte1, 'date')) {
                    continue;
                }
            }

            $stats['total']++;

            // 1. Immatriculation
            $immat = strtoupper(trim((string) ($row[0] ?? '')));
            if ($immat === '') {
                $stats['erreurs'][] = "Ligne $numLigne : Immatriculation manquante.";
                continue;
            }

            // 2. Durée du contrat
            $dureeContrat = null;
            if (isset($row[1]) && trim((string)$row[1]) !== '') {
                $dureeClean = preg_replace('/\D/', '', (string)$row[1]);
                if ($dureeClean !== '') {
                    $dureeContrat = max(0, (int) $dureeClean);
                }
            }

            // 3. Kilométrage max
            $kmMaxi = null;
            if (isset($row[2]) && trim((string)$row[2]) !== '') {
                $kmClean = preg_replace('/\D/', '', (string)$row[2]);
                if ($kmClean !== '') {
                    $kmMaxi = max(0, (int) $kmClean);
                }
            }

            // 4. Date d'entrée
            $dateEntreeRaw = isset($row[3]) ? trim((string)$row[3]) : '';
            $dateEntree = null;
            if ($dateEntreeRaw !== '') {
                $dateEntree = self::parseDateCsv($dateEntreeRaw);
                if ($dateEntree === null) {
                    $stats['erreurs'][] = "Ligne $numLigne ($immat) : Date d'entrée invalide ('$dateEntreeRaw').";
                    continue;
                }
            }

            // Calcul date de sortie prévisionnelle
            $dateSortiePrevue = null;
            if ($dateEntree !== null) {
                $mois = ($dureeContrat !== null && $dureeContrat > 0) ? $dureeContrat : 36;
                $dateSortiePrevue = date('Y-m-d', strtotime("+$mois months", strtotime($dateEntree)));
            }

            // Recherche véhicule existant
            $existant = self::findByImmatriculation($immat);

            if ($existant !== null) {
                if ($mode === 'create_only') {
                    $stats['ignores']++;
                    continue;
                }

                $updateData = [];
                if ($dureeContrat !== null && $dureeContrat > 0) {
                    $updateData['duree_contrat'] = $dureeContrat;
                }
                if ($kmMaxi !== null && $kmMaxi > 0) {
                    $updateData['km_maxi'] = $kmMaxi;
                }
                if ($dateEntree !== null) {
                    $updateData['date_entree'] = $dateEntree;
                    if ($dateSortiePrevue !== null) {
                        $updateData['date_sortie_prevue'] = $dateSortiePrevue;
                    }
                } elseif ($dureeContrat !== null && $dureeContrat > 0 && !empty($existant['date_entree'])) {
                    $updateData['date_sortie_prevue'] = date('Y-m-d', strtotime("+$dureeContrat months", strtotime((string)$existant['date_entree'])));
                }

                if ($updateData !== []) {
                    try {
                        self::update((int)$existant['id'], $updateData);
                        $stats['modifies']++;
                    } catch (\PDOException $e) {
                        $stats['erreurs'][] = "Ligne $numLigne ($immat) : Erreur de mise à jour (" . $e->getMessage() . ").";
                    }
                } else {
                    $stats['ignores']++;
                }
            } else {
                if ($mode === 'update_only') {
                    $stats['ignores']++;
                    continue;
                }

                // Pour un nouveau véhicule, date d'entrée par défaut si absente
                if ($dateEntree === null) {
                    $dateEntree = date('Y-m-d');
                    $mois = ($dureeContrat !== null && $dureeContrat > 0) ? $dureeContrat : 36;
                    $dateSortiePrevue = date('Y-m-d', strtotime("+$mois months", strtotime($dateEntree)));
                }

                $nouvelEnregistrement = [
                    'immatriculation'    => $immat,
                    'modele_id'          => $modeleId,
                    'entite_id'          => $entiteId,
                    'loueur_id'          => $loueurId,
                    'lieu_id'            => $lieuId,
                    'date_entree'        => $dateEntree,
                    'date_sortie_prevue' => $dateSortiePrevue,
                    'duree_contrat'      => $dureeContrat,
                    'km_maxi'            => $kmMaxi,
                    'statut'             => 'actif',
                    'immatricule'        => 0,
                    'kilometrage'        => 0,
                    'hayon'              => 0,
                ];

                try {
                    self::create($nouvelEnregistrement);
                    $stats['crees']++;
                } catch (\PDOException $e) {
                    if (str_contains($e->getMessage(), 'kilometrage')) {
                        unset($nouvelEnregistrement['kilometrage']);
                        try {
                            self::create($nouvelEnregistrement);
                            $stats['crees']++;
                        } catch (\PDOException $e2) {
                            $stats['erreurs'][] = "Ligne $numLigne ($immat) : Erreur de création (" . $e2->getMessage() . ").";
                        }
                    } else {
                        $stats['erreurs'][] = "Ligne $numLigne ($immat) : Erreur de création (" . $e->getMessage() . ").";
                    }
                }
            }
        }

        fclose($handle);
        return $stats;
    }

    /** Règles de validation métier, sous forme de liste de messages. */
    public static function validate(array $d): array
    {
        $erreurs = [];
        if ($d['immatriculation'] === '') {
            $erreurs[] = "L'immatriculation est obligatoire.";
        }
        foreach (['modele_id', 'entite_id', 'loueur_id', 'lieu_id'] as $cle) {
            if ($d[$cle] <= 0) {
                $erreurs[] = 'Toutes les rubriques de nomenclature sont obligatoires.';
                break;
            }
        }
        if ($d['date_entree'] === '') {
            $erreurs[] = "La date d'entrée en flotte est obligatoire.";
        }
        if ($d['date_sortie_prevue'] === '') {
            $erreurs[] = 'La date de sortie prévisionnelle est obligatoire.';
        }
        if ($d['date_entree'] !== '' && $d['date_sortie_prevue'] !== ''
            && $d['date_sortie_prevue'] < $d['date_entree']) {
            $erreurs[] = 'La date de sortie doit être postérieure à la date d\'entrée.';
        }
        if (!empty($d['duree_contrat']) && $d['duree_contrat'] < 0) {
            $erreurs[] = 'La durée du contrat doit être positive ou nulle.';
        }
        if (!empty($d['km_maxi']) && $d['km_maxi'] < 0) {
            $erreurs[] = 'Le kilométrage maximum doit être positif ou nul.';
        }
        if (!empty($d['sortie_reelle']) && $d['sortie_reelle'] < $d['date_entree']) {
            $erreurs[] = 'La sortie réelle ne peut pas être antérieure à la date d\'entrée.';
        }
        if (!empty($d['sortie_angelus']) && $d['sortie_angelus'] < $d['date_entree']) {
            $erreurs[] = 'La sortie Angelus ne peut pas être antérieure à la date d\'entrée.';
        }
        if (!empty($d['sortie_reelle']) && !empty($d['sortie_angelus']) && $d['sortie_angelus'] < $d['sortie_reelle']) {
            $erreurs[] = 'La sortie Angelus ne peut pas être antérieure à la sortie réelle.';
        }
        return array_values(array_unique($erreurs));
    }

    /** Jours restants avant restitution (null si véhicule déjà sorti). */
    public static function joursRestants(array $vehicule): ?int
    {
        if (!empty($vehicule['date_sortie_effective'])) {
            return null;
        }
        $diff = strtotime((string) $vehicule['date_sortie_prevue']) - strtotime(date('Y-m-d'));
        return (int) round($diff / 86400);
    }

    /** Statistiques de parc pour les cartes KPI, avec filtre entité optionnel. */
    public static function stats(?int $entiteId = null): array
    {
        $where = '';
        $params = [];
        if ($entiteId !== null && $entiteId > 0) {
            $where = ' WHERE entite_id = :entite';
            $params['entite'] = $entiteId;
        }

        $row = Database::one("
            SELECT
                COUNT(*) AS total,
                SUM(statut = 'actif')      AS actifs,
                SUM(statut = 'immobilise') AS immobilises,
                SUM(statut = 'sorti')      AS sortis,
                AVG(duree_contrat)          AS duree_contrat_moyenne,
                AVG(km_maxi)               AS km_maxi_moyen
            FROM vehicules{$where}", $params) ?? [];

        $whereAlertes = ' WHERE date_sortie_effective IS NULL AND date_sortie_prevue <= :limite';
        $paramsAlertes = ['limite' => date('Y-m-d', strtotime('+90 days'))];
        if ($entiteId !== null && $entiteId > 0) {
            $whereAlertes .= ' AND entite_id = :entite';
            $paramsAlertes['entite'] = $entiteId;
        }

        $alertes = (int) Database::scalar(
            "SELECT COUNT(*) FROM vehicules{$whereAlertes}",
            $paramsAlertes,
            0
        );

        $total = (int) ($row['total'] ?? 0);

        return [
            'total'        => $total,
            'actifs'       => (int) ($row['actifs'] ?? 0),
            'immobilises'  => (int) ($row['immobilises'] ?? 0),
            'sortis'       => (int) ($row['sortis'] ?? 0),
            'taux_immobilisation' => $total > 0 ? round((int) ($row['immobilises'] ?? 0) * 100 / $total, 1) : 0.0,
            'echeances_90j' => $alertes,
            'duree_contrat_moyenne' => $row['duree_contrat_moyenne'] !== null ? round((float) $row['duree_contrat_moyenne'], 1) : 0.0,
            'km_maxi_moyen' => $row['km_maxi_moyen'] !== null ? round((float) $row['km_maxi_moyen']) : 0,
        ];
    }
}
