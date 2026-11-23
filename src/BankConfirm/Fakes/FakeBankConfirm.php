<?php

namespace Sonole\GovGr\BankConfirm\Fakes;

use Illuminate\Http\Request;
use Sonole\GovGr\BankConfirm\BankConfirm;
use Throwable;

/**
 * BankConfirm without the network: install it with BankConfirm::fake(). confirmAccount() answers from its
 * arguments alone, writes no audit record and needs no credentials.
 *
 * Every IBAN / AFM pair is confirmed unless $confirmations says otherwise: keyed by IBAN, a bool, the service's
 * confirmAccountOutputRecord array (bank / confirmed), or a Throwable to throw.
 */
class FakeBankConfirm extends BankConfirm
{
    /** @param array<string, bool|array<string, mixed>|Throwable> $confirmations keyed by IBAN */
    public function __construct(private readonly array $confirmations = [])
    {
    }

    public function confirmAccount(string $iban, string $afm, ?Request $request = null): array
    {
        $response = $this->confirmations[$iban] ?? true;

        if ($response instanceof Throwable) {
            throw $response;
        }

        $record = is_array($response)
            ? $response
            : ['bank' => 'ΔΟΚΙΜΑΣΤΙΚΗ ΤΡΑΠΕΖΑ', 'confirmed' => $response ? 'true' : 'false'];

        return ['result' => $record, 'audit_record' => null];
    }
}
