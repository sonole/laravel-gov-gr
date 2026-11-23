<?php

namespace Sonole\GovGr\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Greek AMKA: 11 digits, the first 6 the holder's birth date (DDMMYY), the last a Luhn check digit.
 * Use it as a Laravel validation rule, or call ValidAmka::passes() directly, e.g. before a service call.
 */
class ValidAmka implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes(is_scalar($value) ? (string) $value : null)) {
            $fail('govgr::validation.amka')->translate();
        }
    }

    public static function passes(?string $amka): bool
    {
        // Eleven identical digits can be Luhn-valid but are never a real AMKA.
        if ($amka === null || preg_match('/^\d{11}$/', $amka) !== 1 || preg_match('/^(\d)\1{10}$/', $amka) === 1) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 11; $i++) {
            $digit = (int) $amka[$i];

            if (($i + 1) % 2 === 0) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    /** Checks the AMKA before it is sent, unless govgr.validate_input is off. @throws \InvalidArgumentException */
    public static function ensure(string $amka, string $argument = 'amka'): void
    {
        if (config('govgr.validate_input', true) && ! self::passes($amka)) {
            throw new \InvalidArgumentException("[$amka] is not a valid AMKA ($argument).");
        }
    }
}
