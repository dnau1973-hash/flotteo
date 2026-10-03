<?php
declare(strict_types=1);

namespace Core;

/**
 * Convertisseur du sous-ensemble Markdown utilisé par les registres.
 *
 * Ce moteur est **partagé** par deux consommateurs, ce qui est la raison de son
 * extraction hors du script de génération :
 *
 *  - `scripts/build_docs.php`, qui produit les vues HTML autonomes ;
 *  - `Controllers\DocsController`, qui rend le même contenu dans le layout de
 *    l'application.
 *
 * Une seule implémentation garantit que la page vue dans l'application et le
 * fichier HTML compilé ne peuvent pas diverger : aucune dérive documentaire.
 *
 * Le sous-ensemble couvert est volontairement restreint — titres, paragraphes,
 * listes à puces et numérotées (avec repli), tableaux, citations, règles
 * horizontales, blocs de code et traitement en ligne. Aucune dépendance externe,
 * conformément à la règle « pas d'hallucination de dépendances ».
 */
final class Markdown
{
    /**
     * Convertit un document Markdown complet en fragment HTML.
     *
     * La sortie est Trusted Content : elle provient des sources Markdown
     * versionnées avec le projet, jamais d'une saisie utilisateur. Le traitement
     * en ligne échappe le texte avant d'appliquer le balisage (`inline()`), de
     * sorte qu'aucune donnée du document ne peut s'injecter tel quel.
     */
    public static function render(string $md): string
    {
        $lignes = explode("\n", str_replace(["\r\n", "\r"], "\n", $md));
        $html   = [];
        $liste  = null;
        $items  = [];
        $i      = 0;
        $total  = count($lignes);

        $fermerListe = static function (?string &$liste, array &$items) use (&$html): void {
            if ($liste === null) {
                $items = [];
                return;
            }
            $html[] = self::rendreListeItems($items) . '</' . $liste . '>';
            $liste  = null;
            $items  = [];
        };

        while ($i < $total) {
            $ligne = rtrim($lignes[$i]);
            $suivante = $lignes[$i + 1] ?? '';

            // Tableau : | entête | ... | suivi d'une ligne de séparateurs
            if (str_starts_with(ltrim($ligne), '|') && preg_match('/^\s*\|[\s:|-]+\|\s*$/', $suivante) === 1) {
                $fermerListe($liste, $items);
                [$html[], , $i] = self::rendreTableau($lignes, $i);
                continue;
            }

            // Règle horizontale
            if (preg_match('/^(-{3,}|\*{3,})$/', trim($ligne)) === 1) {
                $fermerListe($liste, $items);
                $html[] = '<hr class="my-4">';
                $i++;
                continue;
            }

            // Titres
            if (preg_match('/^(#{1,6})\s+(.*)$/', $ligne, $m) === 1) {
                $fermerListe($liste, $items);
                $niveau = strlen($m[1]);
                $html[] = sprintf('<h%d>%s</h%d>', $niveau, self::inline($m[2]), $niveau);
                $i++;
                continue;
            }

            // Citation
            if (str_starts_with($ligne, '> ')) {
                $fermerListe($liste, $items);
                $html[] = '<blockquote class="blockquote">' . self::inline(substr($ligne, 2)) . '</blockquote>';
                $i++;
                continue;
            }

            // Listes à puces et numérotées
            if (preg_match('/^\s*[-*]\s+(.*)$/', $ligne, $m) === 1) {
                if ($liste !== 'ul') {
                    $fermerListe($liste, $items);
                    $html[] = '<ul>';
                    $liste = 'ul';
                }
                $items[] = [
                    'html'  => self::inline($m[1]),
                    'titre' => (bool) preg_match('/^\*\*.+\*\*:?$/', trim($m[1])),
                ];
                $i = self::agregerContinuation($lignes, $i + 1, $items, $total);
                continue;
            }
            if (preg_match('/^\s*\d+\.\s+(.*)$/', $ligne, $m) === 1) {
                if ($liste !== 'ol') {
                    $fermerListe($liste, $items);
                    $html[] = '<ol>';
                    $liste = 'ol';
                }
                $items[] = ['html' => self::inline($m[1]), 'titre' => false];
                $i = self::agregerContinuation($lignes, $i + 1, $items, $total);
                continue;
            }

            // Bloc de code délimité
            if (str_starts_with($ligne, '```')) {
                $fermerListe($liste, $items);
                $code = [];
                $i++;
                while ($i < $total && !str_starts_with(rtrim($lignes[$i]), '```')) {
                    $code[] = $lignes[$i];
                    $i++;
                }
                $i++;
                $html[] = '<pre class="text-secondary"><code>'
                    . htmlspecialchars(implode("\n", $code), ENT_QUOTES, 'UTF-8') . '</code></pre>';
                continue;
            }

            // Ligne vide : elle ne clôt une liste que si celle-ci est vide de contenu.
            if (trim($ligne) === '') {
                if ($items === []) {
                    $fermerListe($liste, $items);
                }
                $i++;
                continue;
            }
            $fermerListe($liste, $items);
            $debutParagraphe = $i;
            $paragraphe = [];
            while ($i < $total && trim($lignes[$i]) !== ''
                && preg_match('/^(#{1,6}\s|\||-{3,}$|> |```)/', rtrim($lignes[$i])) !== 1) {
                $paragraphe[] = trim($lignes[$i]);
                $i++;
            }

            // La boucle ci-dessus s'arrête sur une ligne de tableau, un titre, une
            // règle ou un bloc de code — mais ces formes ont déjà été traitées plus
            // haut, sauf si la ligne ressemblait à un tableau sans l'être (aucune
            // ligne de séparateurs ne suit). Sans ce garde-fou, l'index n'avancerait
            // jamais et le rendu consommerait la mémoire jusqu'à l'épuisement.
            if ($i === $debutParagraphe) {
                $paragraphe[] = trim($ligne);
                $i++;
            }

            $html[] = '<p>' . self::inline(implode(' ', $paragraphe)) . '</p>';
        }

        $fermerListe($liste, $items);

        return implode("\n", $html);
    }

    /**
     * Agrège les lignes de continuation d'un item de liste (markdown replié)
     * dans le dernier item, afin de ne pas produire de pseudo-paragraphes.
     *
     * @param array<int, array{html: string, titre: bool}> $items
     */
    private static function agregerContinuation(array $lignes, int $i, array &$items, int $total): int
    {
        $dernier = array_key_last($items);
        while ($i < $total) {
            $suivante = rtrim($lignes[$i]);
            if (trim($suivante) === ''
                || preg_match('/^\s*([-*]|\d+\.)\s+/', $suivante) === 1
                || preg_match('/^(#{1,6}\s|\||```|> )/', $suivante) === 1
            ) {
                break;
            }
            $items[$dernier]['html'] .= ' ' . self::inline(trim($suivante));
            $i++;
        }
        return $i;
    }

    /**
     * Rend une liste d'items en imbriquant les enfants sous les titres de section.
     *
     * @param array<int, array{html: string, titre: bool}> $items
     */
    private static function rendreListeItems(array $items): string
    {
        $groupes = [];
        foreach ($items as $item) {
            $dernier = array_key_last($groupes);
            // Les enfants s'accumulent uniquement sous un item de titre.
            if ($item['titre'] || $groupes === [] || $groupes[$dernier]['titre'] === false) {
                $groupes[] = ['titre' => $item['titre'], 'html' => $item['html'], 'enfants' => []];
                continue;
            }
            $groupes[$dernier]['enfants'][] = $item['html'];
        }

        $html = '';
        foreach ($groupes as $groupe) {
            if ($groupe['titre'] && $groupe['enfants'] !== []) {
                $html .= '<li class="fw-bold">' . $groupe['html'] . '<ul>';
                foreach ($groupe['enfants'] as $enfant) {
                    $html .= '<li class="fw-normal">' . $enfant . '</li>';
                }
                $html .= '</ul></li>';
                continue;
            }
            $html .= '<li' . ($groupe['titre'] ? ' class="fw-bold"' : '') . '>' . $groupe['html'] . '</li>';
        }

        return $html;
    }

    /**
     * Rend un tableau Markdown et retourne [html, ligne_entete, index_suivant].
     *
     * @param array<int, string> $lignes
     * @return array{0: string, 1: string, 2: int}
     */
    private static function rendreTableau(array $lignes, int $i): array
    {
        $cellules = static function (string $ligne): array {
            $ligne = trim($ligne);
            $ligne = (string) preg_replace('/^\||\|$/', '', $ligne);
            // Un « \| » est un pipe littéral échappé par l'auteur : il ne délimite
            // aucune colonne. Sans ce traitement, une cellule qui cite une
            // expression régulière (`\|`) était coupée en deux et la ligne perdait
            // sa dernière colonne dans le HTML généré.
            $parts = preg_split('/(?<!\\\\)\|/', $ligne) ?: [$ligne];

            return array_map(
                static fn (string $c): string => str_replace('\\|', '|', trim($c)),
                $parts
            );
        };

        $entete  = $cellules($lignes[$i]);
        $i      += 2;
        $corps  = [];
        $aligns = [];

        // Colonnes d'alignement déduites de la ligne de séparateurs.
        $separateur = $lignes[$i - 1];
        foreach (explode('|', trim($separateur, '| ')) as $c) {
            $aligns[] = str_contains($c, ':') && str_ends_with($c, ':') ? 'center'
                : (str_ends_with($c, ':') ? 'right' : 'left');
        }

        while ($i < count($lignes) && str_starts_with(ltrim(rtrim($lignes[$i])), '|')) {
            $corps[] = $cellules($lignes[$i]);
            $i++;
        }

        $colonnes = count($entete);
        $html = '<div class="table-responsive my-3"><table class="table table-vcenter card-table">';
        $html .= '<thead><tr>';
        foreach ($entete as $index => $cellule) {
            $html .= '<th class="text-' . ($aligns[$index] ?? 'left') . '">' . self::inline($cellule) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($corps as $ligne) {
            if (count($ligne) > $colonnes) {
                // Un « | » non échappé dans le texte d'une colonne produit des
                // cellules excédentaires. Les accoller à la dernière colonne évite
                // la perte de contenu : la boucle ci-dessous n'alignant que sur les
                // colonnes de l'en-tête, et le surplus était donc écarté sans
                // message — la colonne « Résultat » disparaissait de neuf lignes du
                // registre de non-régression.
                $surplus = array_splice($ligne, $colonnes - 1);
                $ligne[]  = implode(' | ', $surplus);
            }

            $html .= '<tr>';
            foreach ($entete as $index => $_) {
                $cellule = $ligne[$index] ?? '';
                $html .= '<td class="text-' . ($aligns[$index] ?? 'left') . '">' . self::inline($cellule) . '</td>';
            }
            $html .= '</tr>';
        }

        return [$html . '</tbody></table></div>', '', $i];
    }

    /** Traitement en ligne : échappement HTML, puis gras, code, liens. */
    private static function inline(string $texte): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        // L'échappement précède le balisage : aucune séquence du document source ne
        // peut être réintroduite comme balise par les substitutions suivantes.
        $texte = $e($texte);

        $texte = (string) preg_replace('/`([^`]+)`/u', '<code>$1</code>', $texte);
        $texte = (string) preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $texte);
        $texte = (string) preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $texte);
        $texte = (string) preg_replace('/\[([^\]]+)\]\(([^)]+)\)/u', '<a href="$2">$1</a>', $texte);

        return $texte;
    }
}