<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

/**
 * Splits a `request`-type span's name -- "{METHOD} {path}", as
 * TraceRequestMiddleware::startTracing builds it -- back into its method
 * and path, and formats a route's accumulated method set back into a
 * single "GET|HEAD"-style string. Shared by RouteStatsQuery and
 * RouteOccurrencesQuery so both classes group and label a route's
 * occurrences the same way.
 */
final class RouteName
{
    /**
     * Fixed verb ordering so "GET|HEAD" always reads in this order rather
     * than drifting with whichever method the window happened to see first.
     *
     * @var list<string>
     */
    private const array METHOD_ORDER = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * @return array{0: string|null, 1: string|null}
     */
    public static function split(string $name): array
    {
        $spaceIndex = strpos($name, ' ');

        if ($spaceIndex === false) {
            return [null, $name === '' ? null : $name];
        }

        return [substr($name, 0, $spaceIndex), substr($name, $spaceIndex + 1)];
    }

    /**
     * @param  list<string>  $methods
     */
    public static function formatMethods(array $methods): string
    {
        if ($methods === []) {
            return '—';
        }

        usort($methods, fn (string $a, string $b): int => self::methodRank($a) <=> self::methodRank($b));

        return implode('|', $methods);
    }

    private static function methodRank(string $method): int
    {
        $index = array_search($method, self::METHOD_ORDER, true);

        return $index === false ? count(self::METHOD_ORDER) : $index;
    }
}
