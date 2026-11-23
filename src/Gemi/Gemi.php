<?php

namespace Sonole\GovGr\Gemi;

use Sonole\GovGr\Core\Exceptions\GovNotFoundException;
use Sonole\GovGr\Core\Support\Normalize;
use Sonole\GovGr\Gemi\Exceptions\AfmNotFoundException;
use Sonole\GovGr\Gemi\Data\BalanceSheet;
use Sonole\GovGr\Gemi\Data\Company;
use Sonole\GovGr\Gemi\Data\CompanyBoard;
use Sonole\GovGr\Gemi\Services\CompanyLookupService;
use Sonole\GovGr\Gemi\Services\ValidationService;
use Illuminate\Http\Request;
use Throwable;

class Gemi
{
    public function __construct(
        private readonly ValidationService $validation,
        private readonly CompanyLookupService $companyLookup,
    ) {
    }

    /**
     * checkAfmExists - confirms an AFM exists in the registry and returns its companies.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => ['companies' => ['company' => [
     *     'gemiNumber' => '000000000000', 'responsibleOrg' => '2', 'isBranch' => 'false', 'legalTypeId' => '1',
     * ]]]]
     *
     * With more than one company, 'company' becomes a list instead of a
     * single associative array (same XML->array quirk noted on
     * confirmRelAmkaMultiple() over in Amka). See companiesFor().
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function validate(string $afm, ?Request $request = null): array
    {
        return $this->validation->validate($afm, $request);
    }

    /**
     * All valid GEMI numbers (padded to 12 digits) across every company the
     * registry returns for $afm, branches included.
     *
     * @return string[]
     *
     * @throws Throwable
     */
    public function getGemiNumbers(string $afm, ?Request $request = null): array
    {
        return $this->validation->getGemiNumbers($afm, $request);
    }

    /** @throws Throwable */
    public function getGemiNumber(string $afm, ?Request $request = null): ?string
    {
        return $this->validation->getGemiNumber($afm, $request);
    }

    /**
     * getCompanyBoardV3 - who sits on the board of the company matching $afm and who represents it.
     * $gemiNumber is resolved via validate()/getGemiNumber() when not given.
     *
     * Natural persons are ->members (the service's fp list), legal persons ->entities (np); either can be
     * empty. Members' home address, birth date and identity document are real, sensitive personal data:
     * handle, log and display with care. The board record as returned is on ->raw.
     *
     * @throws AfmNotFoundException when the AFM has no GEMI number to look up
     * @throws Throwable
     */
    public function companyBoard(string $afm, ?string $gemiNumber = null, ?Request $request = null): CompanyBoard
    {
        $response = $this->companyLookup->companyBoard($afm, $gemiNumber, $request);

        return CompanyBoard::fromRecord((array) ($response['result']['board'] ?? []), $response['audit_record']);
    }

    /**
     * getBasicInfoV3 - the GEMI record of the company matching $afm: legal form, status, address and contact
     * details, key dates, branches, registrations (->applications), activities, capital and shares. The
     * company record as returned is on ->raw.
     *
     * @throws GovNotFoundException when the registry answers without a company record
     * @throws Throwable
     */
    public function basicInfo(string $afm, ?Request $request = null): Company
    {
        $response = $this->companyLookup->basicInfo($afm, $request);
        $record = (array) ($response['result']['company'] ?? []);

        if ($record === []) {
            throw new GovNotFoundException("No GEMI record for AFM [$afm].");
        }

        return Company::fromRecord($record, $response['audit_record']);
    }

    /**
     * companyBalanceSheets - the filed financial statements of the company matching $afm, one BalanceSheet per
     * fiscal year, newest first as the registry sends them. $gemiNumber is resolved via validate()/getGemiNumber()
     * when not given.
     *
     * A year with nothing filed has null figures and no ->files; the registry also sends period labels
     * ("Από 1/1 έως 31/12") as ->year, so it stays a string. Every item shares the call's ->auditRecord.
     *
     * @return list<BalanceSheet>
     *
     * @throws Throwable
     */
    public function balanceSheets(string $afm, ?string $gemiNumber = null, ?Request $request = null): array
    {
        $response = $this->companyLookup->balanceSheets($afm, $gemiNumber, $request);

        return array_map(
            fn (array $item) => BalanceSheet::fromRecord($item, $response['audit_record']),
            Normalize::list($response['result']['companyBalancesheets'] ?? null),
        );
    }
}
