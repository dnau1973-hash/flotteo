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
}
