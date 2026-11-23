<?php

namespace Sonole\GovGr\Gemi\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Sonole\GovGr\Gemi\Fakes\FakeGemi;
use Sonole\GovGr\Gemi\Data\BalanceSheet;
use Sonole\GovGr\Gemi\Data\Company;
use Sonole\GovGr\Gemi\Data\CompanyBoard;

/**
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} validate(string $afm, ?Request $request = null)
 * @method static string[] getGemiNumbers(string $afm, ?Request $request = null)
 * @method static ?string getGemiNumber(string $afm, ?Request $request = null)
 * @method static CompanyBoard companyBoard(string $afm, ?string $gemiNumber = null, ?Request $request = null)
 * @method static Company basicInfo(string $afm, ?Request $request = null)
 * @method static list<BalanceSheet> balanceSheets(string $afm, ?string $gemiNumber = null, ?Request $request = null)
 *
 * @see \Sonole\GovGr\Gemi\Gemi
 */
class Gemi extends Facade
{
    /**
     * Replaces the service with FakeGemi, which never calls the network. See FakeGemi for the answers it
     * gives and how to override single ones.
     *
     * @param array<string, Company|array<string, mixed>|\Throwable> $companies keyed by AFM
     * @param array<string, CompanyBoard|array<string, mixed>|\Throwable> $boards keyed by AFM
     * @param array<string, list<BalanceSheet>|list<array<string, mixed>>|\Throwable> $balanceSheets keyed by AFM
     */
    public static function fake(array $companies = [], array $boards = [], array $balanceSheets = []): FakeGemi
    {
        static::swap($fake = new FakeGemi($companies, $boards, $balanceSheets));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\Gemi\Gemi::class;
    }
}