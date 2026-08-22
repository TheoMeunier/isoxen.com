<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

final class OtlpValue
{
    private const SPAN_KINDS = [
        'SPAN_KIND_UNSPECIFIED' => 0,
        'SPAN_KIND_INTERNAL' => 1,
        'SPAN_KIND_SERVER' => 2,
        'SPAN_KIND_CLIENT' => 3,
        'SPAN_KIND_PRODUCER' => 4,
        'SPAN_KIND_CONSUMER' => 5,
    ];

    private const STATUS_CODES = [
        'STATUS_CODE_UNSET' => 0,
        'STATUS_CODE_OK' => 1,
        'STATUS_CODE_ERROR' => 2,
    ];

    private const SEVERITY_BASE = [
        'UNSPECIFIED' => 0,
        'TRACE' => 1,
        'DEBUG' => 5,
        'INFO' => 9,
        'WARN' => 13,
        'ERROR' => 17,
        'FATAL' => 21,
    ];

    public static function spanKind(mixed $value): ?int
    {
        return self::enum($value, self::SPAN_KINDS);
    }

    public static function statusCode(mixed $value): ?int
    {
        return self::enum($value, self::STATUS_CODES);
    }

    public static function severityNumber(mixed $value): ?int
    {
        if (($numeric = self::numericEnum($value)) !== null) {
            return $numeric;
        }

        if (!is_string($value) || !preg_match('/^SEVERITY_NUMBER_([A-Z]+)([2-4])?$/', $value, $matches)) {
            return null;
        }

        $base = self::SEVERITY_BASE[$matches[1]] ?? null;

        return $base === null ? null : $base + ((int)($matches[2] ?? 1) - 1);
    }

    /**
     * A trace or span id as lowercase hex, whichever way it arrived.
     *
     * @param int $bytes 16 for a trace id, 8 for a span id.
     */
    public static function id(mixed $value, int $bytes): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (strlen($value) === $bytes * 2 && ctype_xdigit($value)) {
            return strtolower($value);
        }

        $decoded = base64_decode($value, true);

        if ($decoded !== false && strlen($decoded) === $bytes) {
            return bin2hex($decoded);
        }

        // Unrecognized but kept when it still fits the column, to aid debugging.
        return strlen($value) <= $bytes * 2 ? $value : null;
    }

    /**
     * @param array<string, int> $map
     */
    private static function enum(mixed $value, array $map): ?int
    {
        if (($numeric = self::numericEnum($value)) !== null) {
            return $numeric;
        }

        return is_string($value) ? ($map[$value] ?? null) : null;
    }

    private static function numericEnum(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && is_numeric($value) ? (int)$value : null;
    }
}
