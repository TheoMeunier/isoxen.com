<?php

namespace Isoxen\Client\Instrumentation\Support;

/**
 * Finds the application code that made a call, by walking the current call
 * stack past every frame that belongs to the framework/this package and
 * stopping at the first one that doesn't -- the same trick Laravel
 * Telescope's query watcher uses to attribute a query to the code that ran
 * it, rather than to the query builder internals that actually executed it.
 */
final class CallerLocator
{
    /**
     * How many frames to walk before giving up. Queries issued from deep
     * inside a relation/scope chain can sit ten or more frames below the
     * application code that triggered them, but an unbounded walk is the
     * expensive part of this -- `debug_backtrace()`'s own $limit argument
     * stops it from building frames beyond this point at all, not just from
     * returning them, so this bounds the cost as well as the search.
     */
    private const int MAX_FRAMES = 30;

    /**
     * @return array{file: string, line: int, class: string|null, function: string|null, type: string|null}|null
     */
    public static function locate(): ?array
    {
        $vendorPath  = rtrim(base_path('vendor'), '/').'/';
        $packagePath = self::ownPackagePath();
        $trace       = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, self::MAX_FRAMES);

        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? null;

            if ($file === null
                || str_starts_with($file, $vendorPath)
                || str_starts_with($file, $packagePath)
            ) {
                continue;
            }

            // A stack frame's own `file`/`line` is the call site, but its
            // `class`/`function` describe what THAT call invoked -- the
            // query builder method, not the code we're looking for. The
            // class and method containing the call site are one frame up:
            // the frame representing whatever was executing at that line.
            $enclosing = $trace[$index + 1] ?? null;

            return [
                'file'     => $file,
                'line'     => $frame['line'] ?? 0,
                'class'    => $enclosing['class']    ?? null,
                'function' => $enclosing['function'] ?? null,
                'type'     => $enclosing['type']      ?? null,
            ];
        }

        return null;
    }

    /**
     * This package's own `src/` directory, as PHP itself resolves it --
     * checking against `base_path('vendor')` alone isn't enough, because
     * during local development this package is a Composer path repository
     * (see composer.json) *symlinked* into vendor/. Depending on how the
     * autoloader was generated, the paths that actually show up in a
     * backtrace can be the symlink's real target (e.g.
     * packages/isoxen-client/src/...) rather than the vendor/ path used to
     * require it -- which is exactly what let the client's own
     * QueryInstrumentation frame slip through as if it were application
     * code. __DIR__ is resolved by PHP the same way those backtrace paths
     * are, so comparing against it is symlink-proof in a way a hardcoded
     * vendor/ prefix isn't.
     */
    private static function ownPackagePath(): string
    {
        return rtrim(dirname(__DIR__, 2), '/').'/';
    }
}
