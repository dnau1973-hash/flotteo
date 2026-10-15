<?php
declare(strict_types=1);

namespace Models;

use Core\Database;
use Core\Logger;
use Core\Request;

/**
 * Tables de paramétrage (dictionnaires) gérées en administration.
 * Chaque dictionnaire est décrit ici : libellé, table, colonnes et champs éditables.
 */
final class Dictionary
{
    public const DOSSIER_LOGO = 'marques';
    public const DOSSIER_PHOTO = 'modeles';
    public const MIMES_LOGO = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Description normalisée des dictionnaires. */
    public const TYPES = [
        'marques' => [
            'libelle'   => 'Marques',
            'table'     => 'marques',
            'champs'    => ['logo', 'nom'],
            'requis'    => ['nom'],
            'etiquette' => ['nom' => 'Nom de la marque', 'logo' => 'Logo'],
            'sources'   => [],
        ],
        'modeles' => [
            'libelle'   => 'Modèles',
            'table'     => 'modeles',
            'champs'    => ['marque_id', 'nom', 'photo'],
            'requis'    => ['marque_id', 'nom'],
            'etiquette' => ['marque_id' => 'Marque', 'nom' => 'Modèle', 'photo' => 'Photo'],
            'sources'   => ['marque' => ['table' => 'marques', 'label' => 'nom']],
        ],
        'entites' => [
            'libelle'  => 'Entités propriétaires',
            'table'    => 'entites',
            'champs'   => ['nom', 'code'],
            'requis'   => ['nom', 'code'],
            'etiquette' => ['nom' => 'Raison sociale', 'code' => 'Code interne'],
            'sources'  => [],
        ],
        'loueurs' => [
            'libelle'  => 'Organismes loueurs',
            'table'    => 'loueurs',
            'champs'   => ['nom', 'contact_email', 'telephone'],
            'requis'   => ['nom'],
            'etiquette' => ['nom' => 'Raison sociale', 'contact_email' => 'Email de contact', 'telephone' => 'Téléphone'],
            'sources'  => [],
        ],
        'lieux' => [
            'libelle'  => 'Lieux d\'exploitation',
            'table'    => 'lieux',
            'champs'   => ['nom', 'ville'],
            'requis'   => ['nom'],
            'etiquette' => ['nom' => 'Site / dépôt', 'ville' => 'Ville'],
            'sources'  => [],
        ],
        'categories_intervention' => [
            'libelle'   => 'Catégories d\'intervention',
            'titre'     => 'Catégorie d\'intervention',
            'table'     => 'categories_intervention',
            'champs'    => ['nom', 'code'],
            'requis'    => ['nom', 'code'],
            'etiquette' => ['nom' => 'Nom de la catégorie', 'code' => 'Code technique (identifiant unique)'],
            'sources'   => [],
        ],
        'types_intervention' => [
            'libelle'   => 'Types d\'intervention',
            'titre'     => 'Type d\'intervention',
            'table'     => 'types_intervention',
            'champs'    => ['libelle', 'categorie'],
            'requis'    => ['libelle'],
            'etiquette' => ['libelle' => 'Libellé de la prestation', 'categorie' => 'Catégorie'],
            'categories' => ['constructeur', 'pneumatique', 'freinage', 'controle', 'carrosserie', 'autre'],
            'sources'   => [],
        ],
    ];

    public static function definition(string $type): ?array
    {
        $def = self::TYPES[$type] ?? null;
        if ($def !== null && $type === 'types_intervention') {
            $def['categories'] = self::categoriesCodes();
            $def['categories_labels'] = self::categoriesLabels();
            $def['categories_liste'] = self::categoriesListe();
        }
        return $def;
    }

    /** Liste les entrées d'un dictionnaire, avec résolution des clés étrangères. */
    public static function list(string $type): array
    {
        if ($type === 'categories_intervention' || $type === 'types_intervention') {
            self::ensureCategoriesTable();
        }

        $def = self::definition($type);
        if ($def === null) {
            return [];
        }
        $champs = ['id', ...$def['champs']];

        try {
            $orderBy = ($type === 'categories_intervention') ? 'nom ASC' : 'id ASC';
            $lignes = Database::all('SELECT ' . implode(', ', $champs) . ' FROM ' . $def['table'] . ' ORDER BY ' . $orderBy);
        } catch (\PDOException $e) {
            Logger::error("Lecture dictionnaire $type impossible", $e);
            return [];
        }

        // Ajoute le libellé résolu de chaque clé étrangère (ex: marque d'un modèle).
        foreach ($def['sources'] as $prefixeFk => $source) {
            try {
                $ref = Database::all('SELECT id, ' . $source['label'] . ' AS libelle FROM ' . $source['table']);
            } catch (\PDOException $e) {
                Logger::error("Dictionnaire source {$source['table']} inaccessible", $e);
                continue;
            }
            $index = [];
            foreach ($ref as $r) {
                $index[(int) $r['id']] = (string) $r['libelle'];
            }
            $fk = $prefixeFk . '_id';
            foreach ($lignes as &$ligne) {
                if (isset($ligne[$fk], $index[(int) $ligne[$fk]])) {
                    $ligne[$prefixeFk . '_libelle'] = $index[(int) $ligne[$fk]];
                }
            }
            unset($ligne);
        }

        return $lignes;
    }

    public static function buildPayload(string $type): array
    {
        $def = self::definition($type);
        if ($def === null) {
            return [];
        }
        $data = [];
        foreach ($def['champs'] as $champ) {
            if ($champ === 'logo' || $champ === 'photo') {
                continue; // Les images téléversées sont traitées séparément
            }
            $valeur = (string) Request::input($champ, '');
            if (str_ends_with($champ, '_id')) {
                $data[$champ] = (int) $valeur;
            } elseif ($champ === 'categorie') {
                $categories = $def['categories'] ?? self::categoriesCodes();
                $data[$champ] = in_array($valeur, $categories, true) ? $valeur : ($categories[0] ?? 'autre');
            } elseif ($type === 'categories_intervention' && $champ === 'code') {
                $code = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($valeur)));
                if ($code === '') {
                    $nom = (string) Request::input('nom', '');
                    $code = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($nom)));
                    $code = trim($code, '_');
                }
                $data[$champ] = mb_substr($code !== '' ? $code : 'cat_' . time(), 0, 50);
            } else {
                $data[$champ] = mb_substr(trim($valeur), 0, 190);
            }
        }
        return $data;
    }

    public static function validate(string $type, array $d, ?int $horsId = null): array
    {
        $def = self::definition($type);
        if ($def === null) {
            return ['Dictionnaire inconnu.'];
        }
        $erreurs = [];
        foreach ($def['requis'] as $champ) {
            if (str_ends_with($champ, '_id')) {
                if (($d[$champ] ?? 0) <= 0) {
                    $erreurs[] = $def['etiquette'][$champ] . ' est obligatoire.';
                }
            } elseif (trim((string) ($d[$champ] ?? '')) === '') {
                $erreurs[] = $def['etiquette'][$champ] . ' est obligatoire.';
            }
        }
        if ($type === 'types_intervention' && isset($d['categorie'])) {
            $categories = $def['categories'] ?? self::categoriesCodes();
            if (!in_array($d['categorie'], $categories, true)) {
                $erreurs[] = 'Catégorie invalide.';
            }
        }
        if ($type === 'categories_intervention') {
            $code = (string) ($d['code'] ?? '');
            if ($code === '') {
                $erreurs[] = 'Le code de la catégorie est obligatoire.';
            } elseif (!preg_match('/^[a-z0-9_-]+$/', $code)) {
                $erreurs[] = 'Le code ne peut contenir que des lettres minuscules sans accent, des chiffres, des tirets et des underscores.';
            } else {
                self::ensureCategoriesTable();
                $sql = 'SELECT id FROM categories_intervention WHERE code = :code';
                $params = ['code' => $code];
                if ($horsId !== null && $horsId > 0) {
                    $sql .= ' AND id != :id';
                    $params['id'] = $horsId;
                }
                try {
                    if (Database::scalar($sql, $params) !== null) {
                        $erreurs[] = 'Ce code de catégorie est déjà utilisé par une autre catégorie.';
                    }
                } catch (\PDOException) {}
            }
        }
        return $erreurs;
    }

    public static function create(string $type, array $d): int
    {
        if ($type === 'categories_intervention') {
            self::ensureCategoriesTable();
        }
        return Database::insert(self::definition($type)['table'], $d);
    }

    public static function update(string $type, int $id, array $d): int
    {
        if ($type === 'categories_intervention') {
            self::ensureCategoriesTable();
            try {
                $ancien = Database::one('SELECT code FROM categories_intervention WHERE id = :id', ['id' => $id]);
                if ($ancien !== null && !empty($d['code']) && $ancien['code'] !== $d['code']) {
                    Database::run('UPDATE types_intervention SET categorie = :new WHERE categorie = :old', [
                        'new' => $d['code'],
                        'old' => $ancien['code'],
                    ]);
                }
            } catch (\PDOException $e) {
                Logger::error("Synchronisation code catégorie intervention échouée: " . $e->getMessage(), $e);
            }
        }
        return Database::update(self::definition($type)['table'], $id, $d);
    }

    public static function delete(string $type, int $id): bool
    {
        $table = self::definition($type)['table'];
        $fichierASupprimer = null;
        $dossierSuppr = null;
        if ($type === 'marques') {
            try {
                $fichierASupprimer = Database::scalar('SELECT logo FROM marques WHERE id = :id', ['id' => $id]);
                $dossierSuppr = self::DOSSIER_LOGO;
            } catch (\PDOException $e) { \Core\Logger::error($e->getMessage()); }
        } elseif ($type === 'modeles') {
            try {
                $fichierASupprimer = Database::scalar('SELECT photo FROM modeles WHERE id = :id', ['id' => $id]);
                $dossierSuppr = self::DOSSIER_PHOTO;
            } catch (\PDOException $e) { \Core\Logger::error($e->getMessage()); }
        } elseif ($type === 'categories_intervention') {
            self::ensureCategoriesTable();
            try {
                $cat = Database::one('SELECT code FROM categories_intervention WHERE id = :id', ['id' => $id]);
                if ($cat !== null) {
                    $code = (string) $cat['code'];
                    $nb = (int) Database::scalar('SELECT COUNT(*) FROM types_intervention WHERE categorie = :code', ['code' => $code]);
                    if ($nb > 0) {
                        return false; // Référencée par au moins un type d'intervention
                    }
                }
            } catch (\PDOException $e) {
                Logger::error("Contrôle référence catégorie intervention #$id échoué", $e);
                return false;
            }
        }

        try {
            $supprime = Database::delete($table, $id) > 0;
            if ($supprime && !empty($fichierASupprimer) && $dossierSuppr !== null) {
                \Core\Upload::remove($dossierSuppr, (string) $fichierASupprimer);
            }
            return $supprime;
        } catch (\PDOException $e) {
            // Contrainte d'intégrité : entrée encore référencée par un véhicule ou un entretien.
            Logger::error("Suppression $table#$id refusée (référence existante)", $e);
            return false;
        }
    }

    /** Assure l'existence et l'initialisation de la table categories_intervention. */
    public static function ensureCategoriesTable(): void
    {
        static $initialise = false;
        if ($initialise) {
            return;
        }

        try {
            Database::pdo()->exec("CREATE TABLE IF NOT EXISTS categories_intervention (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                nom VARCHAR(100) NOT NULL,
                actif TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            try {
                Database::pdo()->exec("ALTER TABLE types_intervention MODIFY categorie VARCHAR(80) NOT NULL DEFAULT 'autre'");
            } catch (\PDOException) {}

            $count = (int) Database::scalar("SELECT COUNT(*) FROM categories_intervention");
            if ($count === 0) {
                $defauts = [
                    ['code' => 'constructeur', 'nom' => 'Entretien constructeur'],
                    ['code' => 'pneumatique',   'nom' => 'Pneumatiques'],
                    ['code' => 'freinage',      'nom' => 'Freinage'],
                    ['code' => 'controle',      'nom' => 'Contrôle technique'],
                    ['code' => 'carrosserie',   'nom' => 'Carrosserie'],
                    ['code' => 'autre',         'nom' => 'Autre'],
                ];
                foreach ($defauts as $d) {
                    Database::insert('categories_intervention', $d);
                }
            }
            $initialise = true;
        } catch (\Throwable $e) {
            Logger::error("Initialisation table categories_intervention échouée: " . $e->getMessage(), $e);
        }
    }

    /** Retourne la liste des catégories avec id, code et nom. */
    public static function categoriesListe(): array
    {
        self::ensureCategoriesTable();
        try {
            $cats = Database::all('SELECT id, code, nom FROM categories_intervention ORDER BY nom ASC');
            if ($cats !== []) {
                return $cats;
            }
        } catch (\PDOException $e) {
            Logger::error('Lecture categories_intervention impossible', $e);
        }

        return [
            ['id' => 1, 'code' => 'constructeur', 'nom' => 'Entretien constructeur'],
            ['id' => 2, 'code' => 'pneumatique',   'nom' => 'Pneumatiques'],
            ['id' => 3, 'code' => 'freinage',      'nom' => 'Freinage'],
            ['id' => 4, 'code' => 'controle',      'nom' => 'Contrôle technique'],
            ['id' => 5, 'code' => 'carrosserie',   'nom' => 'Carrosserie'],
            ['id' => 6, 'code' => 'autre',         'nom' => 'Autre'],
        ];
    }

    /** Retourne les codes uniques de toutes les catégories. */
    public static function categoriesCodes(): array
    {
        return array_column(self::categoriesListe(), 'code');
    }

    /** Retourne une table de correspondance [code => libellé]. */
    public static function categoriesLabels(): array
    {
        $labels = [];
        foreach (self::categoriesListe() as $c) {
            $labels[(string) $c['code']] = (string) $c['nom'];
        }
        return $labels;
    }

    /** URL publique du logo d'une marque, ou null. */
    public static function urlLogo(?string $logo): ?string
    {
        if (empty($logo)) {
            return null;
        }
        return \Core\Url::upload(self::DOSSIER_LOGO . '/' . $logo);
    }

    /** URL publique de la photo d'un modèle, ou null. */
    public static function urlPhoto(?string $photo): ?string
    {
        if (empty($photo)) {
            return null;
        }
        return \Core\Url::upload(self::DOSSIER_PHOTO . '/' . $photo);
    }
}
