<?php

namespace Sonole\GovGr\Gemi;

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
     * getCompanyBoardV3 - board members and representatives for the company matching $afm.
     * $gemiNumber is resolved via validate()/getGemiNumber() when not given.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => ['board' => [
     *     'fp' => [[
     *         'afm' => '000000000', 'lastname' => 'ΠΑΠΑΔΟΠΟΥΛΟΣ', 'firstname' => 'ΓΙΑΝΝΗΣ',
     *         'homeCity' => 'ΑΘΗΝΑ', 'homeStreet' => 'ΟΔΟΣ ΔΕΙΓΜΑ', 'homeNumber' => '1', 'zipCode' => '10000',
     *         'isRepresentativeAlone' => 'false', 'isRepresentativeInCommon' => 'false',
     *         'relationDescr' => 'ΠΡΟΕΔΡΟΣ', 'relationId' => '1', 'relationType' => 'BOARD',
     *         'codeFP' => '000000', 'fathersName' => 'ΠΕΤΡΟΣ', 'mothersName' => 'ΜΑΡΙΑ',
     *         'activeMember' => 'true', 'electionDate' => '2020-01-01', 'startDate' => '2020-01-01',
     *         'endDate' => '2025-01-01', 'identityNumber' => 'ΑΒ000000', 'identityType' => 'ΑΣΤΥΝΟΜΙΚΗ ΤΑΥΤΟΤΗΤΑ',
     *     ]],
     *     'np' => [],
     * ]]]
     *
     * 'fp' (φυσικά πρόσωπα - natural persons) and 'np' (νομικά πρόσωπα -
     * legal persons acting as representatives) are each lists; either can be
     * empty. Board members' home address and identity document fields are
     * real, sensitive personal data - handle/log/display with care.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function companyBoard(string $afm, ?string $gemiNumber = null, ?Request $request = null): array
    {
        return $this->companyLookup->companyBoard($afm, $gemiNumber, $request);
    }

    /**
     * getBasicInfoV3 - basic registry info for the company matching $afm.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => ['company' => [
     *     'arGemi' => '000000000000', 'afm' => '000000000', 'legalTypeId' => '1', 'legalTypeDescr' => 'ΑΕ',
     *     'memberStatusId' => '3', 'memberStatusDescr' => 'Ενεργή', 'doyId' => '0000', 'doyDescr' => 'ΔΕΙΓΜΑ ΔΟΥ',
     *     'businessName' => 'ΔΕΙΓΜΑ ΑΝΩΝΥΜΗ ΕΤΑΙΡΕΙΑ', 'businessTitles' => ['ΔΕΙΓΜΑ ΑΕ'],
     *     'companyGoal' => 'Σκοπός της Εταιρίας είναι...', 'orgId' => '2', 'orgDescr' => 'ΕΠΙΜΕΛΗΤΗΡΙΟ',
     *     'prefectureId' => '1', 'prefectureDescr' => 'ΑΘΗΝΩΝ', 'municipalityId' => '1', 'municipalityDescr' => 'ΑΘΗΝΑ',
     *     'street' => 'ΟΔΟΣ ΔΕΙΓΜΑ', 'streetNumber' => '1', 'tk' => '10000', 'city' => 'ΑΘΗΝΑ', 'region' => 'ΑΘΗΝΑ',
     *     'phone' => '2100000000', 'fax' => '2100000000',
     *     'dateGemiRegistered' => '2020-01-01', 'dateProfessionStarted' => '2020-01-01', 'dateIncorporated' => '2020-01-01',
     *     'arMae' => '00000/00/Β/00/0000', 'email' => 'example@example.com', 'dateLastChange' => '2026-01-01',
     *     'departmentId' => '1', 'departmentDescr' => 'ΔΕΙΓΜΑ', 'duration' => '50', 'expirationDate' => '2070-01-01',
     *     'branch' => [],
     *     'businessApplications' => [[
     *         'kak' => '000000', 'appSubjectId' => '1', 'appSubjectDescr' => 'ΔΕΙΓΜΑ',
     *         'appDate' => '2020-01-01', 'appDecisionDate' => '2020-01-01', 'appDecisionStatusId' => '2',
     *         'appDecisionStatusDescr' => 'ΚΑΤΑΧΩΡΙΣΗ', 'appRegistrationDate' => '2020-01-01',
     *         'url' => 'https://www.businessregistry.gr/downloadFile/index?key=assemblyDecision&elementId=000000',
     *         'deletionSubject' => 'false',
     *     ]],
     *     'businessSubjects' => [[
     *         'professionId' => '00000', 'professionCode' => '00000000', 'professionDescr' => 'ΔΕΙΓΜΑ', 'startDate' => '2020-01-01',
     *     ]],
     *     'capital' => [],
     *     'stocks' => [['amount' => '2500', 'stockTypeId' => '1', 'stockType' => 'Ονομαστικές, Κοινές']],
     * ]]]
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function basicInfo(string $afm, ?Request $request = null): array
    {
        return $this->companyLookup->basicInfo($afm, $request);
    }

    /**
     * companyBalanceSheets - balance sheet / financial statement data for
     * the company matching $afm and its GEMI number. $gemiNumber is
     * resolved via validate()/getGemiNumber() when not given.
     *
     * Example (field names shown, values are placeholders, not from a real call):
     * ['result' => ['companyBalancesheets' => [
     *     ['year' => '2011', 'files' => []],
     *     [
     *         'year' => '2019', 'netTurnover' => '0.00', 'totalAssets' => '0.00', 'numStuff' => '3', 'isXbrl' => false,
     *         'files' => [['fileId' => '000000', 'arGemi' => '000000000000', 'type' => 'balancesheet']],
     *     ],
     * ]]]
     *
     * A year with no filed balance sheet comes back with just 'year' and an
     * empty 'files'; later years add the financial fields shown above.
     *
     * @return array{result: array<string, mixed>, audit_record: array<string, mixed>}
     *
     * @throws Throwable
     */
    public function balanceSheets(string $afm, ?string $gemiNumber = null, ?Request $request = null): array
    {
        return $this->companyLookup->balanceSheets($afm, $gemiNumber, $request);
    }
}