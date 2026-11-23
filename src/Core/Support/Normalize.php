<?php

namespace Sonole\GovGr\Core\Support;

use Carbon\Carbon;
use Throwable;

/** Cleans the loosely typed values the SOAP services return, once, for every data object. */
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
