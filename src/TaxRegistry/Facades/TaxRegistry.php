<?php

namespace Sonole\GovGr\TaxRegistry\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} retrieveInfosByADT(string $adt, int $kind, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} retrieveInfoByName(string $surnameA, string $fathersFirstName, ?string $surnameB = null, ?string $nameA = null, ?string $nameB = null, ?string $mothersFirstName = null, ?string $birthYear = null, ?string $birthMonth = null, ?string $birthDay = null, ?string $carPlateNumber = null, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} retrieveInfoByAFM(string $afm, ?string $firmActivitiesRequest = null, ?string $membershipsRequest = null, ?string $relationsRequest = null, ?string $symmetoxesRequest = null, ?string $sysxetiseisRequest = null, ?string $branchActivitiesRequest = null, ?string $branchAbroadRequest = null, ?string $firmAbroadRequest = null, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>|null} lookupByAfm(string $afm, ?string $afmCalledBy = null, ?Request $request = null)
 *
 * @see \Sonole\GovGr\TaxRegistry\TaxRegistry
 */
class TaxRegistry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\TaxRegistry\TaxRegistry::class;
    }
}