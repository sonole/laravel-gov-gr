<?php

namespace Sonole\GovGr\BankConfirm;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Services\AuditedSoapService;
use Sonole\GovGr\Core\Support\XmlHelper;
use Illuminate\Http\Request;
use Throwable;

class BankConfirm extends AuditedSoapService
{
    protected function service(): GovService
    {
        return GovService::BankConfirm;
    }

    protected function configKey(): string
    {
        return 'bank-confirm';
    }

    protected function namespacePrefix(): string
    {
        return 'cbk';
    }

    protected function namespaceUri(): string
    {
        return 'http://gsis.ggps.interoperability/confirmBankInterface';
    }

    /**
     * confirmAccount - whether $iban is confirmed usable by $afm.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => ['bank' => 'ΤΡΑΠΕΖΑ Α', 'confirmed' => 'true']]
     *
     * confirmed reflects whether the IBAN/AFM combination matches; a
     * mismatch on its own is not an error. A business rejection (e.g.
     * malformed IBAN) instead comes back as an errorCode sitting inside
     * confirmAccountOutputRecord itself, alongside bank/confirmed - not
     * under the general errorRecord sibling - so it's checked explicitly.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function confirmAccount(string $iban, string $afm, ?Request $request = null): array
    {
        $body = XmlHelper::tag('iban', $iban).XmlHelper::tag('afm', $afm);

        return $this->call('confirmAccount', $body, $request, function (array $output) {
            $errorCode = data_get($output, 'errorCode');

            if (! empty($errorCode)) {
                throw $this->newException(data_get($output, 'errorDescr', $errorCode));
            }

            return $output;
        });
    }
}
