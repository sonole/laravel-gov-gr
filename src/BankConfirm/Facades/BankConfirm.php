<?php

namespace Sonole\GovGr\BankConfirm\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Sonole\GovGr\BankConfirm\Fakes\FakeBankConfirm;

/**
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} confirmAccount(string $iban, string $afm, ?Request $request = null)
 *
 * @see \Sonole\GovGr\BankConfirm\BankConfirm
 */
class BankConfirm extends Facade
{
    /**
     * Replaces the service with FakeBankConfirm, which never calls the network. See FakeBankConfirm for the answers it
     * gives and how to override single ones.
     *
     * @param array<string, bool|array<string, mixed>|\Throwable> $confirmations keyed by IBAN
     */
    public static function fake(array $confirmations = []): FakeBankConfirm
    {
        static::swap($fake = new FakeBankConfirm($confirmations));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\BankConfirm\BankConfirm::class;
    }
}