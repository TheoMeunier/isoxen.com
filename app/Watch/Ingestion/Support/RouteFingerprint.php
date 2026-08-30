<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

/**
 * A stable id for "the same route" across occurrences, used to group the
 * Requests page's Routes table and to address a single route's detail
 * page/drawer -- the path is the identity RouteStatsQuery already groups
 * by, so this just hashes it into a short, URL-safe id (mirrors
 * QueryFingerprint/ExceptionFingerprint's role for queries/exceptions; a
 * raw path can't be used directly in a URL segment since it may itself
 * contain slashes and route-parameter braces, e.g. "/users/{id}").
 */
final class RouteFingerprint
{
    public static function make(string $path): string
    {
        return hash('sha256', $path);
    }
}
