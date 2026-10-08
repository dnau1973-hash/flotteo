<?php
declare(strict_types=1);

namespace Core;

/**
 * Helpers de requête HTTP (entrées, JSON, détection AJAX).
 */
final class Request
{
    /** @var array<string, string> Paramètres capturés par le routeur (segments {param}). */
    private static array $params = [];

    public static function setParams(array $params): void
    {
        self::$params = $params;
    }

    public static function param(string $nom, string $defaut = ''): string
    {
        $val = self::$params[$nom] ?? $defaut;
        return is_string($val) ? $val : $defaut;
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function input(string $cle, mixed $defaut = null): mixed
    {
        $valeur = $_POST[$cle] ?? $_GET[$cle] ?? $defaut;
        return is_string($valeur) ? trim($valeur) : $valeur;
    }

    public static function int(string $cle, int $defaut = 0): int
    {
        $val = self::input($cle, $defaut);
        return is_numeric($val) ? (int) $val : $defaut;
    }

    public static function float(string $cle, float $defaut = 0.0): float
    {
        $val = self::input($cle, $defaut);
        return is_numeric($val) ? (float) $val : $defaut;
    }

    /** Valeur de date au format Y-m-d ou chaîne vide. */
    public static function date(string $cle, string $defaut = ''): string
    {
        $val = (string) self::input($cle, $defaut);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) === 1 ? $val : '';
    }

    public static function isJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public static function rawBody(): string
    {
        static $body = null;
        return $body ??= (string) file_get_contents('php://input');
    }

    /**
     * Le corps de la requête a-t-il dépassé `post_max_size` ?
     *
     * PHP traite ce dépassement sans lever la moindre erreur : il vide
     * silencieusement `$_POST` **et** `$_FILES`, et ne renseigne aucun
     * `UPLOAD_ERR_*`. Un formulaire envoyant un fichier proche de la limite
     * applicative arrive donc au contrôleur comme s'il n'avait rien envoyé —
     * le contrôle de CSRF échoue en premier, et l'utilisateur voit une erreur
     * qui ne mentionne ni le fichier ni sa taille.
     *
     * PHP ne signale pas le dépassement ; il se déduit du corps reçu, plus long
     * que la limite alors que les champs sont vides. La comparaison est faite sur
     * une chaîne, `post_max_size` portant souvent un suffixe (`8M`).
     *
     * @return int|null Octets acceptés par PHP, ou null si la limite est inconnue
     */
    public static function postMaxSize(): ?int
    {
        $brut = trim((string) ini_get('post_max_size'));
        if ($brut === '' || (int) $brut === 0) {
            return null;
        }

        $unite = strtoupper(substr($brut, -1));
        $valeur = (int) $brut;

        return match ($unite) {
            'G'     => $valeur * 1024 * 1024 * 1024,
            'M'     => $valeur * 1024 * 1024,
            'K'     => $valeur * 1024,
            default => $valeur,
        };
    }

    /** Le corps reçu a-t-il été tronqué par `post_max_size` ? */
    public static function corpsTronque(): bool
    {
        $limite = self::postMaxSize();
        if ($limite === null) {
            return false;
        }

        // Seule une soumission multipart transporte un fichier. Un corps JSON
        // volumineux est lui aussi tronqué, mais le message « fichier refusé »
        // serait faux : le dépôt n'est pas en cause.
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (!str_starts_with($type, 'multipart/form-data')) {
            return false;
        }

        $contenu = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

        return $contenu > $limite && $_POST === [] && $_FILES === [];
    }
}
