<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

/**
 * A stable id for "the same exception" across occurrences, used to group the
 * Exceptions table and to address a single exception's detail page. Keyed by
 * type+file+line when the client reported a file/line (the common case,
 * since the client SDK captures those from getFile()/getLine() whenever an
 * exception is recorded), falling back to type+message so an exception
 * without that data still groups instead of every occurrence spawning its
 * own row.
 */
final class ExceptionFingerprint
{
    public static function make(string $type, ?string $file, ?int $line, ?string $message): string
    {
        $identity = ($file !== null && $line !== null)
            ? "{$type}|{$file}|{$line}"
            : "{$type}|{$message}";

        return hash('sha256', $identity);
    }
}
