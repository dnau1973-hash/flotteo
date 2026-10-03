<?php
declare(strict_types=1);

namespace Core;

/**
 * Journalisation interne sur disque. Aucune trace technique n'est exposée à l'écran.
 */
final class Logger
{
    private static ?string $file = null;

    private static function path(): string
    {
        if (self::$file === null) {
            $dir = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            self::$file = $dir . '/app-' . date('Y-m') . '.log';
        }
        return self::$file;
    }

    public static function error(string $message, ?\Throwable $e = null): void
    {
        $line = sprintf(
            '[%s] %s%s',
            date('Y-m-d H:i:s'),
            $message,
            $e !== null ? ' | ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : ''
        );
        @file_put_contents(self::path(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
