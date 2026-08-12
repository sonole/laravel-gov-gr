<?php

namespace Sonole\GovGr\BankConfirm\Facades;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array{result: array<string, mixed>, audit_record: array<string, mixed>} confirmAccount(string $iban, string $afm, ?Request $request = null)
 *
 * @see \Sonole\GovGr\BankConfirm\BankConfirm
 */
class BankConfirm extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\BankConfirm\BankConfirm::class;
    }
}