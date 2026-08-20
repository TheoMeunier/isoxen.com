<?php

declare(strict_types=1);

namespace Isoxen\Client\Support;

use Throwable;

/**
 * Where this package reports its own failures.
 *
 * Deliberately not the application's logger. That logger is instrumented:
 * a log line about a failed export becomes a log record, which is exported,
 * which fails, which logs. The loop that produced a 15 GB log file started
 * exactly there.
 *
 * So failures go two places that nothing here listens to — stderr (so they
 * surface in `docker compose logs`) and a dedicated file next to the
 * application's own logs, which survives the process and can be read after
 * the fact. The file is capped, because a diagnostic channel that can fill
 * a disk is a bug of the same family as the one it exists to report.
 */
final class Diagnostics
{
    /** Past this size the file is started over rather than grown. */
    private const MAX_BYTES = 1_048_576;

    public static function write(string $message): void
    {
        $line = sprintf('[%s] %s', date('Y-m-d H:i:s'), $message);

        // stderr first: it is the one destination that cannot fail.
        error_log('isoxen: '.$message);

        try {
            $path = self::path();

            if ($path === null) {
                return;
            }

            if (is_file($path) && filesize($path) > self::MAX_BYTES) {
                file_put_contents($path, "--- truncated ---\n");
            }

            file_put_contents($path, $line."\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // A diagnostic channel must never become the thing that breaks.
        }
    }

    private static function path(): ?string
    {
        if (! function_exists('storage_path')) {
            return null;
        }

        $directory = storage_path('logs');

        return is_dir($directory) && is_writable($directory)
            ? $directory.DIRECTORY_SEPARATOR.'isoxen.log'
            : null;
    }
}
