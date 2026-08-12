<?php

namespace Sonole\GovGr\Ergani\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} employeeRelation(string $afm, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} employmentRelationship(string $afm, string $refdate, ?Request $request = null)
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} employmentHistoryByPeriod(string $afm, string $refdateFrom, string $refdateTo, ?Request $request = null)
 *
 * @see \Sonole\GovGr\Ergani\Ergani
 */
class Ergani extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\Ergani\Ergani::class;
    }
}