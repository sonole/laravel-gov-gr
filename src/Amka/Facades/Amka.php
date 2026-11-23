<?php

namespace Sonole\GovGr\Amka\Facades;

use Illuminate\Http\Request;
use Sonole\GovGr\Amka\Fakes\FakeAmka;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Sonole\GovGr\Amka\Data\RelationshipConfirmation confirmRelAmka(string $relAmka, ?string $parentAmka = null, ?string $parentFirstname = null, ?string $parentSurname = null, ?string $parentFathername = null, ?string $parentMothername = null, ?string $parentBirthdate = null, ?Request $request = null)
 * @method static \Sonole\GovGr\Amka\Data\RelationshipConfirmation[] confirmRelAmkaMultiple(string[] $relAmkas, ?string $parentAmka = null, ?string $parentFirstname = null, ?string $parentSurname = null, ?string $parentFathername = null, ?string $parentMothername = null, ?string $parentBirthdate = null, ?Request $request = null)
 * @method static \Sonole\GovGr\Amka\Data\AmkaPerson amka2data(string $amka, ?Request $request = null)
 * @method static \Sonole\GovGr\Amka\Data\AmkaPerson data2amka(string $surname, string $firstName, ?string $fatherName = null, ?string $motherName = null, ?string $birthDate = null, ?string $afm = null, ?string $adt = null, ?Request $request = null)
 * @method static \Sonole\GovGr\Amka\Data\AmkaPerson amka2dataE(string $amka, ?Request $request = null)
 * @method static \Sonole\GovGr\Amka\Data\AmkaPerson amka2dataLE(string $amka, ?Request $request = null)
 *
 * @see \Sonole\GovGr\Amka\Amka
 */
class Amka extends Facade
{
    /**
     * Replaces the service with FakeAmka, which never calls the network. See FakeAmka for the answers it gives
     * and how to override single ones.
     *
     * @param array<string, \Sonole\GovGr\Amka\Data\AmkaPerson|array<string, mixed>|\Throwable> $amka2data keyed by AMKA
     * @param array<string, \Sonole\GovGr\Amka\Data\AmkaPerson|array<string, mixed>|\Throwable> $data2amka keyed by AFM
     * @param array<string, \Sonole\GovGr\Amka\Data\RelationshipConfirmation|bool|\Throwable> $confirmations keyed by relAmka
     */
    public static function fake(array $amka2data = [], array $data2amka = [], array $confirmations = []): FakeAmka
    {
        static::swap($fake = new FakeAmka($amka2data, $data2amka, $confirmations));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\Amka\Amka::class;
    }
}