<?php

namespace Sonole\GovGr\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Greek AFM: 9 digits, the 9th a check digit (modulo 11 over the first 8, weighted 2^8 ... 2^1).
 * Use it as a Laravel validation rule, or call ValidAfm::passes() directly, e.g. before a service call.
 */
class ValidAfm implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes(is_scalar($value) ? (string) $value : null)) {
            $fail('govgr::validation.afm')->translate();
        }
    }

    public static function passes(?string $afm): bool
    {
        // "000000000" satisfies the modulo but is no real AFM.
        if ($afm === null || preg_match('/^\d{9}$/', $afm) !== 1 || $afm === '000000000') {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 8; $i++) {
            $sum += (int) $afm[$i] << (8 - $i);
        }

        return (int) $afm[8] === $sum % 11 % 10;
    }

    /** Checks the AFM before it is sent, unless govgr.validate_input is off. @throws \InvalidArgumentException */
    public static function ensure(string $afm, string $argument = 'afm'): void
    {
        if (config('govgr.validate_input', true) && ! self::passes($afm)) {
            throw new \InvalidArgumentException("[$afm] is not a valid AFM ($argument).");
        }
    }
}
