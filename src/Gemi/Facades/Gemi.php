<?php

namespace Sonole\GovGr\Gemi\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} validate(string $afm, ?Request $request = null)
 * @method static string[] getGemiNumbers(string $afm, ?Request $request = null)
 * @method static ?string getGemiNumber(string $afm, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} companyBoard(string $afm, ?string $gemiNumber = null, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} basicInfo(string $afm, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} balanceSheets(string $afm, ?string $gemiNumber = null, ?Request $request = null)
 *
 * @see \Sonole\GovGr\Gemi\Gemi
 */
class Gemi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\Gemi\Gemi::class;
    }
}