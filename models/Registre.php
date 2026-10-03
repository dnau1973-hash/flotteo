<?php
declare(strict_types=1);

namespace Models;

/**
 * Description déclarative des registres documentaires de l'application.
 *
 * Modèle de référence unique : `Controllers\DocsController` (rendu dans le layout
 * de l'application) et `scripts/build_docs.php` (vues HTML autonomes) lisent tous
 * deux cette table. Déclarer les quatre documents à un seul endroit évite qu'un
 * titre ou une icône ne diverge entre la page vue dans l'application et le fichier
 * compilé — dérive documentaire impossible par construction.
 *
 * Le schéma reprend celui de `Models\Dictionary::TYPES` : la liste des documents
 * vit en données, jamais dupliquée dans le code des vues.
 */
final class Registre
{
    /**
     * Les quatre registres, indexés par clé d'URL (`/docs/{cle}`).
     *
     * @var array<string, array{
     *     fichier: string,      source Markdown, sous docs/
     *     libelle: string,      libellé court : barre de navigation et pied de page
     *     titre: string,        titre complet du document
     *     icone: string,        glyphe du sous-ensemble Font Awesome embarqué
     *     resume: string,       description courte affichée sous le libellé
     * }>
     */
    public const DOCUMENTS = [
        'user_guide' => [
            'fichier' => 'user_guide.md',
            'libelle' => 'Guide utilisateur',
            'titre'   => "Guide de l'utilisateur",
            'icone'   => 'book',
            'resume'  => 'Prise en main, écrans et diagnostic des pannes',
        ],
        'changelog' => [
            'fichier' => 'changelog.md',
            'libelle' => 'Journal des modifications',
            'titre'   => 'Journal des modifications',
            'icone'   => 'file-lines',
            'resume'  => 'Historique des livraisons et correctifs',
        ],
        'features' => [
            'fichier' => 'features.md',
            'libelle' => 'Fonctionnalités',
            'titre'   => 'Registre des fonctionnalités',
            'icone'   => 'list',
            'resume'  => 'Périmètre fonctionnel et état des contrôles',
        ],
        'qa_recette' => [
            'fichier' => 'qa_recette.md',
            'libelle' => 'Recette QA',
            'titre'   => 'Registre de recette & qualité',
            'icone'   => 'check',
            'resume'  => 'Cas de test, incidents et vérifications',
        ],
    ];

    /**
     * Tous les registres, dans l'ordre d'affichage.
     *
     * @return array<string, array<string, string>>
     */
    public static function tous(): array
    {
        return self::DOCUMENTS;
    }

    /**
     * Registre demandé, ou null si la clé est inconnue.
     *
     * @return array<string, string>|null
     */
    public static function obtenir(string $cle): ?array
    {
        return self::DOCUMENTS[$cle] ?? null;
    }

    /** Chemin absolu de la source Markdown d'un registre. */
    public static function source(string $cle): ?string
    {
        $document = self::obtenir($cle);
        if ($document === null) {
            return null;
        }

        $chemin = dirname(__DIR__) . '/docs/' . $document['fichier'];

        return is_file($chemin) ? $chemin : null;
    }
}