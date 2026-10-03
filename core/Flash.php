<?php
declare(strict_types=1);

namespace Core;

/**
 * Messages flash : un passage en session, restitution par toasts Tabler.
 */
final class Flash
{
    private const CLE = '_flash';

    public static function add(string $type, string $message): void
    {
        Auth::start();
        $_SESSION[self::CLE][] = ['type' => $type, 'message' => $message];
    }

    public static function pull(): array
    {
        Auth::start();
        $messages = $_SESSION[self::CLE] ?? [];
        unset($_SESSION[self::CLE]);
        return $messages;
    }
}
