<?php

namespace Sonole\GovGr\Amka\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} confirmRelAmka(string $relAmka, ?string $parentAmka = null, ?string $parentFirstname = null, ?string $parentSurname = null, ?string $parentFathername = null, ?string $parentMothername = null, ?string $parentBirthdate = null, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} confirmRelAmkaMultiple(string[] $relAmkas, ?string $parentAmka = null, ?string $parentFirstname = null, ?string $parentSurname = null, ?string $parentFathername = null, ?string $parentMothername = null, ?string $parentBirthdate = null, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} amka2data(string $amka, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} data2amka(string $surname, string $firstName, ?string $fatherName = null, ?string $motherName = null, ?string $birthDate = null, ?string $afm = null, ?string $adt = null, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} amka2dataE(string $amka, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} amka2dataLE(string $amka, ?Request $request = null)
 *
 * @see \Sonole\GovGr\Amka\Amka
 */
class Amka extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\Amka\Amka::class;
    }
}