<?php

namespace Sonole\GovGr\Core\Support;

use Carbon\Carbon;
use Throwable;

/** Cleans the loosely typed values the SOAP and REST services return, once, for every data object. */
final class Normalize
{
    /** A trimmed string, or null for what XML gives for an empty tag (an empty array) and for blank values. */
    public static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /**
     * text() of $record[$key].
     *
     * @param  array<string, mixed>  $record
     */
    public static function field(array $record, string $key): ?string
    {
        return self::text($record[$key] ?? null);
    }

    /** A "true" / "false" (any case, or the JSON boolean, or "1" / "0"), or null for anything else. */
    public static function bool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower((string) self::text($value))) {
            'true', '1' => true,
            'false', '0' => false,
            default => null,
        };
    }

    /** A number the services send as a string ("2600.00", "12345"), or null when the value is not numeric. */
    public static function number(mixed $value): ?float
    {
        $text = self::text($value);

        return $text !== null && is_numeric($text) ? (float) $text : null;
    }

    /**
     * A list of records. XML gives one item as a single record and several as a list, and an empty tag as an
     * empty array; JSON gives a proper list. The result is always a list, possibly empty.
     *
     * @return list<array<string, mixed>>
     */
    public static function list(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        return array_is_list($value) ? array_values(array_filter($value, 'is_array')) : [$value];
    }

    /**
     * A list of non-blank strings, from a single value or a list of them.
     *
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(array_map([self::class, 'text'], $values)));
    }

    /**
     * A full timestamp ("2025-11-27T17:12:00.85+02:00") or a plain date ("2025-11-27"), or null when the value
     * is neither. Unlike date(), the time and zone are kept.
     */
    public static function dateTime(mixed $value): ?Carbon
    {
        $value = self::text($value);

        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A date in one of $formats (tried in order), or null when the value is none. A "Y-m-d" may carry a time
     * and zone after it ("1990-01-02T00:00:00+02:00").
     *
     * @param  list<string>  $formats
     */
    public static function date(mixed $value, array $formats = ['Y-m-d']): ?Carbon
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        foreach ($formats as $format) {
            $candidate = $format === 'Y-m-d' ? substr($value, 0, 10) : $value;

            try {
                $date = Carbon::createFromFormat('!'.$format, $candidate);
            } catch (Throwable) {
                continue;
            }

            if ($date && $date->format($format) === $candidate) {
                return $date;
            }
        }

        return null;
    }
}
