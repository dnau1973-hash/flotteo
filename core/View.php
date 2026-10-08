<?php
declare(strict_types=1);

namespace Core;

/**
 * Moteur de vue : layout + inclusion, échappement strict des sorties.
 */
final class View
{
    private string $root;
    private array $data = [];

    /** Scripts additionnels injectés en fin de page. */
    private array $scripts = [];

    /** Feuilles de style additionnelles injectées dans l'en-tête. */
    private array $styles = [];

    public function __construct(string $root = '')
    {
        $this->root = $root !== '' ? $root : dirname(__DIR__) . '/views';
    }

    /** Déclare un script supplémentaire pour la page courante. */
    public function useScript(string $fichier): void
    {
        $this->scripts[$fichier] = $fichier;
    }

    /**
     * Déclare une feuille de style supplémentaire pour la page courante.
     *
     * Le chemin est donné relatif à `public/assets`, comme pour `useScript()`.
     * Elle est émise dans l'en-tête et non dans le corps : une feuille déclarée
     * au milieu du document fonctionne, mais elle applique ses règles après
     * l'affichage initial et retarde le premier rendu.
     */
    public function useStyle(string $fichier): void
    {
        $this->styles[$fichier] = $fichier;
    }

    public function share(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /** Affiche une vue brute (sans layout). */
    public function partial(string $template, array $data = []): void
    {
        $fichier = $this->root . '/' . $template . '.php';
        if (!is_file($fichier)) {
            Logger::error("Vue introuvable: $template");
            http_response_code(500);
            echo 'Erreur interne : vue manquante.';
            return;
        }
        extract(array_merge($this->data, $data), EXTR_SKIP);
        require $fichier;
    }

    /** Affiche une vue encapsulée dans le layout principal. */
    public function render(string $template, array $data = [], bool $layout = true): void
    {
        $contenu = $this->capture($template, $data);
        if (!$layout) {
            echo $contenu;
            return;
        }
        $scripts = $this->scripts;
        $styles = $this->styles;
        require $this->root . '/layout/header.php';
        echo $contenu;
        require $this->root . '/layout/footer.php';
    }

    /** Capture la sortie d'une vue en mémoire. */
    public function capture(string $template, array $data = []): string
    {
        ob_start();
        $this->partial($template, $data);
        return (string) ob_get_clean();
    }
}
