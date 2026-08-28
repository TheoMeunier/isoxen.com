<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

/**
 * A stable id for "the same statement" across occurrences, used to group the
 * Queries table and to address a single query's detail page/drawer -- the
 * exact SQL text is the identity (see QueryStatsQuery), the same choice
 * made when the columns were designed, so this just hashes it into a short,
 * URL-safe id (mirrors ExceptionFingerprint's role for exceptions).
 */
final class QueryFingerprint
{
    /**
     * The group a statement falls into when the client SDK didn't report
     * `db.query.text` at all -- shared with QueryStatsQuery and
     * QueryOccurrencesQuery so both classes fingerprint that group the same
     * way.
     */
    public const string UNKNOWN_QUERY = '(unknown query)';

    public static function make(string $sql): string
    {
        return hash('sha256', $sql);
    }
}
