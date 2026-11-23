<?php

namespace Sonole\GovGr\TaxRegistry\Facades;

use Illuminate\Http\Request;
use Sonole\GovGr\TaxRegistry\Fakes\FakeTaxRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} retrieveInfosByADT(string $adt, int $kind, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} retrieveInfoByName(string $surnameA, string $fathersFirstName, ?string $surnameB = null, ?string $nameA = null, ?string $nameB = null, ?string $mothersFirstName = null, ?string $birthYear = null, ?string $birthMonth = null, ?string $birthDay = null, ?string $carPlateNumber = null, ?Request $request = null)
 * @method static \Sonole\GovGr\TaxRegistry\Data\RegistryEntity retrieveInfoByAFM(string $afm, ?string $firmActivitiesRequest = null, ?string $membershipsRequest = null, ?string $relationsRequest = null, ?string $symmetoxesRequest = null, ?string $sysxetiseisRequest = null, ?string $branchActivitiesRequest = null, ?string $branchAbroadRequest = null, ?string $firmAbroadRequest = null, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>|null} lookupByAfm(string $afm, ?string $afmCalledBy = null, ?Request $request = null)
 *
 * @see \Sonole\GovGr\TaxRegistry\TaxRegistry
 */
class TaxRegistry extends Facade
{
    /**
     * Replaces the service with FakeTaxRegistry, which never calls the network. See FakeTaxRegistry for the
     * answers it gives and how to override single ones.
     *
     * @param array<string, \Sonole\GovGr\TaxRegistry\Data\RegistryEntity|array<string, mixed>|\Throwable> $responses keyed by AFM
     */
    public static function fake(array $responses = []): FakeTaxRegistry
    {
        static::swap($fake = new FakeTaxRegistry($responses));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\TaxRegistry\TaxRegistry::class;
    }
}