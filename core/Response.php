<?php
declare(strict_types=1);

namespace Core;

/**
 * Réponses HTTP : redirection, JSON, fichier.
 */
final class Response
{
    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public static function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function back(string $fallback = '/'): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        self::redirect($ref !== '' ? $ref : $fallback);
    }
}
