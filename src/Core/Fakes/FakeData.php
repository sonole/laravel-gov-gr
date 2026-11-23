<?php

namespace Sonole\GovGr\Core\Fakes;

use Carbon\Carbon;

/** Deterministic pieces the fake services build their answers from: the same input always gives the same person. */
final class FakeData
{
    /** The AMKA check digit: Luhn over the first 10 digits, doubling the 2nd, 4th, ..., 10th. */
    public static function amkaCheckDigit(string $first10): int
    {
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $digit = (int) $first10[$i];

            if (($i + 1) % 2 === 0) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
        }

        return (10 - $sum % 10) % 10;
    }

    public static function isValidAmka(string $amka): bool
    {
        return preg_match('/^\d{11}$/', $amka) === 1
            && preg_match('/^(\d)\1{10}$/', $amka) !== 1
            && self::amkaCheckDigit(substr($amka, 0, 10)) === (int) $amka[10];
    }

    /** A valid AMKA that starts with the birth date (DDMMYY) and is stable for the same $seed. */
    public static function amka(Carbon $birthDate, string $seed): string
    {
        $first10 = $birthDate->format('dmy').str_pad((string) (crc32($seed) % 10000), 4, '0', STR_PAD_LEFT);

        return $first10.self::amkaCheckDigit($first10);
    }

    /** The birth date an AMKA starts with. Two-digit years: a future date means the previous century. */
    public static function birthDateOf(string $amka): Carbon
    {
        $date = Carbon::createFromFormat('!dmy', substr($amka, 0, 6)) ?: Carbon::now()->setDate(1980, 1, 1)->startOfDay();

        return $date->isFuture() ? $date->subYears(100) : $date;
    }

    /** A date in 1955-1999 that is stable for the same $seed. */
    public static function birthDate(string $seed): Carbon
    {
        $hash = crc32($seed);

        return Carbon::now()->setDate(1955 + $hash % 45, 1 + $hash % 12, 1 + $hash % 28)->startOfDay();
    }
}
