<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Support;

/**
 * Decodes the `attributes` arrays used throughout OTLP/JSON payloads
 * (resource attributes, span attributes, log attributes, ...) into plain
 * associative arrays.
 */
final class OtlpAttributes
{
    /**
     * @param  array<int, array{key?: string, value?: array<string, mixed>}>  $attributes
     * @return array<string, mixed>
     */
    public static function toArray(array $attributes): array
    {
        $decoded = [];

        foreach ($attributes as $attribute) {
            $key = $attribute['key'] ?? null;

            if (! is_string($key)) {
                continue;
            }

            $decoded[$key] = self::decodeValue($attribute['value'] ?? []);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function decodeValue(array $value): mixed
    {
        return match (true) {
            array_key_exists('stringValue', $value) => $value['stringValue'],
            array_key_exists('boolValue', $value) => (bool) $value['boolValue'],
            array_key_exists('intValue', $value) => (int) $value['intValue'],
            array_key_exists('doubleValue', $value) => (float) $value['doubleValue'],
            array_key_exists('arrayValue', $value) => array_map(
                fn (array $item): mixed => self::decodeValue($item),
                $value['arrayValue']['values'] ?? [],
            ),
            array_key_exists('kvlistValue', $value) => self::toArray($value['kvlistValue']['values'] ?? []),
            default => null,
        };
    }
}
