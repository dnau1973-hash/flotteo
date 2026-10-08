<?php
declare(strict_types=1);

namespace Core;

/**
 * Journalisation interne sur disque. Aucune trace technique n'est exposée à l'écran.
 *
 * Deux severités seulement. `error()` porte les défaillances et les anomalies ;
 * `info()` porte le déroulé d'exploitation — rotation, externalisation — qui
 * serait trop bruyant dans un journal d'erreurs, mais doit rester consultable
 * lorsqu'une sauvegarde planifiée ne s'est pas produite comme prévu.
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
        self::ecrire('ERREUR', $message, $e);
    }

    /** Tracé d'exploitation, consultable dans le même fichier. */
    public static function info(string $message): void
    {
        self::ecrire('INFO', $message, null);
    }

    private static function ecrire(string $niveau, string $message, ?\Throwable $e): void
    {
        $line = sprintf(
            '[%s] %-6s %s%s',
            date('Y-m-d H:i:s'),
            $niveau,
            $message,
            $e !== null ? ' | ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : ''
        );
        @file_put_contents(self::path(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
