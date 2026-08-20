<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

/**
 * Normalizes the OTLP/JSON values whose encoding is not agreed on between
 * implementations.
 *
 * OTLP/JSON has two representations in the wild and a collector has to
 * accept both:
 *
 * - **Enums.** The spec allows the numeric value, but protobuf's canonical
 *   JSON mapping emits the *name* — `"SPAN_KIND_SERVER"` rather than `2`.
 *   Most SDKs serialize through protobuf, so names are the common case.
 *   Handed straight to Postgres, a name lands in a smallint column and the
 *   insert throws, which loses the whole batch.
 * - **IDs.** The spec says trace/span ids are hex strings; protobuf's JSON
 *   mapping encodes `bytes` as base64. Storing base64 doesn't fail, but it
 *   silently breaks every join between a log line and its trace.
 */
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

    /** Each severity name has four levels: INFO, INFO2, INFO3, INFO4. */
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

        if (! is_string($value) || ! preg_match('/^SEVERITY_NUMBER_([A-Z]+)([2-4])?$/', $value, $matches)) {
            return null;
        }

        $base = self::SEVERITY_BASE[$matches[1]] ?? null;

        return $base === null ? null : $base + ((int) ($matches[2] ?? 1) - 1);
    }

    /**
     * A trace or span id as lowercase hex, whichever way it arrived.
     *
     * @param  int  $bytes  Length of the id: 16 for a trace id, 8 for a span id.
     */
    public static function id(mixed $value, int $bytes): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (strlen($value) === $bytes * 2 && ctype_xdigit($value)) {
            return strtolower($value);
        }

        $decoded = base64_decode($value, true);

        if ($decoded !== false && strlen($decoded) === $bytes) {
            return bin2hex($decoded);
        }

        // Unrecognized, but kept rather than dropped when it still fits the
        // column — an id we can't interpret is worth more when debugging
        // than a null that hides that anything arrived at all.
        return strlen($value) <= $bytes * 2 ? $value : null;
    }

    /**
     * @param  array<string, int>  $map
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

        return is_string($value) && is_numeric($value) ? (int) $value : null;
    }
}
