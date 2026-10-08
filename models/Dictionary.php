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
        'types_intervention' => [
            'libelle'  => 'Types d\'intervention',
            'table'    => 'types_intervention',
            'champs'   => ['libelle', 'categorie'],
            'requis'   => ['libelle'],
            'etiquette' => ['libelle' => 'Libellé de la prestation', 'categorie' => 'Catégorie'],
            'categories' => ['constructeur', 'pneumatique', 'freinage', 'controle', 'carrosserie', 'autre'],
            'sources'  => [],
        ],
    ];

    public static function definition(string $type): ?array
    {
        return self::TYPES[$type] ?? null;
    }

    /** Liste les entrées d'un dictionnaire, avec résolution des clés étrangères. */
    public static function list(string $type): array
    {
        $def = self::definition($type);
        if ($def === null) {
            return [];
        }
        $champs = ['id', ...$def['champs']];

        try {
            $lignes = Database::all('SELECT ' . implode(', ', $champs) . ' FROM ' . $def['table'] . ' ORDER BY id ASC');
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
                $data[$champ] = in_array($valeur, $def['categories'] ?? [], true) ? $valeur : 'autre';
            } else {
                $data[$champ] = mb_substr($valeur, 0, 190);
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
        if ($type === 'types_intervention' && isset($d['categorie'])
            && !in_array($d['categorie'], $def['categories'], true)) {
            $erreurs[] = 'Catégorie invalide.';
        }
        return $erreurs;
    }

    public static function create(string $type, array $d): int
    {
        return Database::insert(self::definition($type)['table'], $d);
    }

    public static function update(string $type, int $id, array $d): int
    {
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
