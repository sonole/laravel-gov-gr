<?php

namespace Sonole\GovGr\Gemi\Services;

use Sonole\GovGr\Core\Contracts\IdentityResolver;
use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Http\GovHttpClient;
use Sonole\GovGr\Core\Services\AuditedRestService;
use Illuminate\Http\Request;
use Throwable;

class CompanyLookupService extends AuditedRestService
{
    public function __construct(
        IdentityResolver $identityResolver,
        GovHttpClient $http,
        private readonly ValidationService $validation,
    ) {
        parent::__construct($identityResolver, $http);
    }

    protected function service(): GovService
    {
        return GovService::Gemi;
    }

    protected function configKey(): string
    {
        return 'gemi';
    }

    /**
     * getCompanyBoardV3 - board members and representatives for the company matching $afm.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function companyBoard(string $afm, ?string $gemiNumber = null, ?Request $request = null): array
    {
        $gemiNumber ??= $this->validation->resolveGemiNumber($afm, $request);

        return $this->call('board', 'getCompanyBoardV3', ['afm' => $afm, 'arGemi' => $gemiNumber], $request);
    }

    /**
     * getBasicInfoV3 - basic registry info for the company matching $afm.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function basicInfo(string $afm, ?Request $request = null): array
    {
        return $this->call('basic_info', 'getBasicInfoV3', ['afm' => $afm], $request);
    }

    /**
     * companyBalanceSheets - balance sheet / financial statement data for
     * the company matching $afm and its GEMI number.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function balanceSheets(string $afm, ?string $gemiNumber = null, ?Request $request = null): array
    {
        $gemiNumber ??= $this->validation->resolveGemiNumber($afm, $request);

        return $this->call('balance_sheets', 'companyBalanceSheets', ['afm' => $afm, 'id' => $gemiNumber], $request);
    }
}