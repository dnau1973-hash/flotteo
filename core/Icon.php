<?php
declare(strict_types=1);

namespace Core;

/**
 * Fabrique d'icônes Font Awesome.
 *
 * Font Awesome est auto-hébergé (voir public/assets/css/fontawesome.css) : la
 * feuille de style est élaguée aux seules icônes réellement utilisées, ce qui
 * interdit d'appeler un glyphe absent — `Icon::solid()` lève alors une
 * `InvalidArgumentException` en développement plutôt que d'afficher un carré.
 *
 * Usage : <?= Icon::solid('car') ?>  /  <?= Icon::solid('car', 'me-2') ?>
 */
final class Icon
{
    /** Familles et styles CSS autorisés, en dur : aucune valeur libre en sortie. */
    private const FAMILLES = [
        'fa-solid'   => 'fa-solid',
        'fa-regular' => 'fa-regular',
        'fa-brands'  => 'fa-brands',
    ];

    /**
     * Glyphes déclarés dans le sous-ensemble embarqué.
     *
     * Cette liste doit rester synchronisée avec public/assets/css/fontawesome.css :
     * c'est la seule garantie qu'une icône demandée sera réellement rendue.
     */
     private const GLYPHES = [
         'car', 'gauge-high', 'screwdriver-wrench', 'triangle-exclamation', 'gear',
         'users', 'user', 'right-from-bracket', 'bars', 'bell', 'book', 'file-lines',
         'plus', 'trash-can', 'pen', 'magnifying-glass', 'check', 'xmark', 'envelope',
         'eye', 'wrench', 'calendar-days', 'chart-line', 'circle-info', 'lock',
         'chevron-down', 'list', 'gears', 'sliders', 'shield-halved', 'database',
         'file-pdf', 'boxes', 'tag', 'arrow-right-arrow-left',
         // Module de sauvegarde : conditionnement, partage réseau, disque, retour en haut.
         'box-archive', 'network-wired', 'folder-open', 'download', 'server',
         'circle-check', 'clock', 'arrow-up',
         // Familles « brands ».
         'github', 'slack',
     ];

    /** Icône pleine (`fa-solid`). */
    public static function solid(string $nom, string $classes = ''): string
    {
        return self::rendre($nom, 'fa-solid', $classes);
    }

    /** Icône de marque (`fa-brands`). */
    public static function brands(string $nom, string $classes = ''): string
    {
        return self::rendre($nom, 'fa-brands', $classes);
    }

    /** Vrai si le glyphe est couvert par le sous-ensemble embarqué. */
    public static function disponible(string $nom): bool
    {
        return in_array($nom, self::GLYPHES, true);
    }

    private static function rendre(string $nom, string $famille, string $classes): string
    {
        $style = self::FAMILLES[$famille] ?? null;
        if ($style === null) {
            throw new \InvalidArgumentException('Famille d\'icônes inconnue : ' . $famille);
        }

        if (!self::disponible($nom)) {
            // Ne pas rendre silencieusement un glyphe absent : le carré vide
            // serait bien plus difficile à diagnostiquer qu'une exception.
            throw new \InvalidArgumentException(sprintf(
                'Icône « %s » absente du sous-ensemble Font Awesome embarqué : ajoutez son glyphe dans public/assets/css/fontawesome.css et dans Core\Icon::GLYPHES.',
                $nom
            ));
        }

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        return '<i class="' . $e($style) . ' fa-' . $e($nom)
            . ($classes !== '' ? ' ' . $e($classes) : '')
            . '" aria-hidden="true"></i>';
    }
}