<?php

namespace Sonole\GovGr\Gemi\Fakes;

use Illuminate\Http\Request;
use Sonole\GovGr\Core\Fakes\FakeData;
use Sonole\GovGr\Gemi\Data\BalanceSheet;
use Sonole\GovGr\Gemi\Data\Company;
use Sonole\GovGr\Gemi\Data\CompanyBoard;
use Sonole\GovGr\Gemi\Exceptions\AfmNotFoundException;
use Sonole\GovGr\Gemi\Gemi;
use Throwable;

/**
 * Gemi without the network: install it with Gemi::fake(). Every method answers from the AFM alone, writes no
 * audit record and needs no credentials.
 *
 * Any 9-digit AFM is a registered ΙΚΕ whose GEMI number, name and dates derive from the AFM; anything else is
 * not in the registry (getGemiNumber() is null, the lookups throw AfmNotFoundException). Override single AFMs
 * with $companies, $boards and $balanceSheets, each keyed by AFM: the object, the service's record array (the
 * company / board record, or the companyBalancesheets list), or a Throwable to throw.
 */
class FakeGemi extends Gemi
{
    /**
     * @param array<string, Company|array<string, mixed>|Throwable> $companies
     * @param array<string, CompanyBoard|array<string, mixed>|Throwable> $boards
     * @param array<string, list<BalanceSheet>|list<array<string, mixed>>|Throwable> $balanceSheets
     */
    public function __construct(
        private readonly array $companies = [],
        private readonly array $boards = [],
        private readonly array $balanceSheets = [],
    ) {
    }

    public function validate(string $afm, ?Request $request = null): array
    {
        $number = $this->getGemiNumber($afm);

        return [
            'result' => ['companies' => $number === null ? [] : ['company' => [
                'gemiNumber' => $number, 'responsibleOrg' => '1', 'isBranch' => 'false', 'legalTypeId' => '19',
            ]]],
            'audit_record' => null,
        ];
    }

    public function getGemiNumbers(string $afm, ?Request $request = null): array
    {
        $number = $this->getGemiNumber($afm);

        return $number === null ? [] : [$number];
    }

    public function getGemiNumber(string $afm, ?Request $request = null): ?string
    {
        return preg_match('/^\d{9}$/', $afm) === 1 ? substr($afm, 0, 9).'000' : null;
    }

    public function companyBoard(string $afm, ?string $gemiNumber = null, ?Request $request = null): CompanyBoard
    {
        $response = $this->override($this->boards, $afm);

        if ($response instanceof CompanyBoard) {
            return $response;
        }

        if (is_array($response)) {
            return CompanyBoard::fromRecord($response);
        }

        $this->ensureRegistered($afm);
        $hash = crc32($afm);
        $surnames = ['ΓΕΩΡΓΙΟΥ', 'ΝΙΚΟΛΑΟΥ', 'ΔΗΜΗΤΡΙΟΥ', 'ΙΩΑΝΝΟΥ'];
        $names = ['ΓΕΩΡΓΙΟΣ', 'ΜΑΡΙΑ', 'ΝΙΚΟΛΑΟΣ', 'ΕΛΕΝΗ'];

        return CompanyBoard::fromRecord(['fp' => [[
            'afm' => str_pad((string) ($hash % 1000000000), 9, '0', STR_PAD_LEFT),
            'lastname' => $surnames[$hash % 4], 'firstname' => $names[intdiv($hash, 4) % 4], 'fathersName' => 'ΑΝΤΩΝΙΟΣ',
            'homeCity' => 'ΑΘΗΝΑ', 'homeStreet' => 'ΔΟΚΙΜΑΣΤΙΚΗ ΟΔΟΣ', 'homeNumber' => '10', 'zipCode' => '12345',
            'isRepresentativeAlone' => 'true', 'isRepresentativeInCommon' => 'false',
            'relationDescr' => 'Διαχειριστής', 'relationId' => '12', 'relationType' => 'BOARD', 'codeFP' => (string) ($hash % 100000),
            'activeMember' => 'true', 'electionDate' => '2020-01-01', 'startDate' => '2020-01-01',
            'identityNumber' => 'ΑΒ000000', 'identityType' => 'ΑΣΤΥΝΟΜΙΚΗ ΤΑΥΤΟΤΗΤΑ',
        ]], 'np' => []]);
    }

    public function basicInfo(string $afm, ?Request $request = null): Company
    {
        $response = $this->override($this->companies, $afm);

        if ($response instanceof Company) {
            return $response;
        }

        if (is_array($response)) {
            return Company::fromRecord($response);
        }

        $this->ensureRegistered($afm);
        $incorporated = FakeData::birthDate($afm)->addYears(40);

        return Company::fromRecord([
            'arGemi' => $this->getGemiNumber($afm), 'afm' => $afm, 'legalTypeId' => '19', 'legalTypeDescr' => 'ΙΚΕ',
            'memberStatusId' => '3', 'memberStatusDescr' => 'Ενεργή', 'doyId' => '1117', 'doyDescr' => 'Δ.Ο.Υ. ΙΖ ΑΘΗΝΩΝ',
            'businessName' => "ΔΟΚΙΜΑΣΤΙΚΗ $afm ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ", 'businessTitles' => ["ΔΟΚΙΜΑΣΤΙΚΗ $afm"],
            'companyGoal' => 'Δοκιμαστικός σκοπός.', 'orgId' => '1', 'orgDescr' => 'ΕΜΠΟΡΙΚΟ & ΒΙΟΜΗΧΑΝΙΚΟ ΕΠΙΜΕΛΗΤΗΡΙΟ ΑΘΗΝΩΝ',
            'prefectureId' => '5', 'prefectureDescr' => 'ΑΤΤΙΚΗΣ', 'municipalityId' => '5001', 'municipalityDescr' => 'ΑΘΗΝΑΙΩΝ / ΑΤΤΙΚΗΣ',
            'street' => 'ΔΟΚΙΜΑΣΤΙΚΗ ΟΔΟΣ', 'streetNumber' => '10', 'tk' => '12345', 'city' => 'ΑΘΗΝΑ', 'region' => 'ΑΘΗΝΑ',
            'phone' => '2101234567', 'email' => 'example@example.com',
            'dateGemiRegistered' => $incorporated->format('Y-m-d'), 'dateProfessionStarted' => $incorporated->format('Y-m-d'),
            'dateRegistered' => $incorporated->format('Y-m-d'), 'dateIncorporated' => $incorporated->format('Y-m-d'),
            'lastStateChangeDate' => $incorporated->format('Y-m-d'), 'dateLastChange' => $incorporated->format('Y-m-d'),
            'departmentId' => '2', 'departmentDescr' => 'Υπηρεσιών', 'duration' => '20',
            'expirationDate' => $incorporated->copy()->addYears(20)->format('Y-m-d'),
            'branch' => [],
            'businessApplications' => [],
            'businessSubjects' => [[
                'professionId' => '27314', 'professionCode' => '62010000', 'professionDescr' => 'ΔΡΑΣΤΗΡΙΟΤΗΤΕΣ ΠΡΟΓΡΑΜΜΑΤΙΣΜΟΥ ΗΛΕΚΤΡΟΝΙΚΩΝ ΣΥΣΤΗΜΑΤΩΝ',
                'startDate' => $incorporated->format('Y-m-d'),
            ]],
            'capital' => ['capitalStock' => '5000', 'currency' => 'Euro'],
            'stocks' => [['amount' => '5000', 'nominalPrice' => '1', 'stockTypeId' => '7', 'stockType' => 'Κεφαλαιικές']],
        ]);
    }

    public function balanceSheets(string $afm, ?string $gemiNumber = null, ?Request $request = null): array
    {
        $response = $this->override($this->balanceSheets, $afm);

        if (is_array($response)) {
            return array_map(fn ($item) => $item instanceof BalanceSheet ? $item : BalanceSheet::fromRecord($item), $response);
        }

        $this->ensureRegistered($afm);
        $gemiNumber ??= $this->getGemiNumber($afm);
        $year = (int) now()->subYear()->format('Y');

        return [
            BalanceSheet::fromRecord([
                'year' => (string) $year, 'netTurnover' => '120000', 'totalAssets' => '85000', 'numStuff' => '3',
                'sumAssets' => '40000', 'sumLiabilities' => '45000', 'preTaxResults' => '15000', 'isXbrl' => false,
                'files' => [['fileId' => '100001', 'arGemi' => $gemiNumber, 'type' => 'balancesheet']],
            ]),
            BalanceSheet::fromRecord(['year' => (string) ($year - 1), 'isXbrl' => false, 'files' => []]),
        ];
    }

    /**
     * @param array<string, mixed> $responses
     *
     * @throws Throwable
     */
    private function override(array $responses, string $afm): mixed
    {
        $response = $responses[$afm] ?? null;

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }

    private function ensureRegistered(string $afm): void
    {
        if ($this->getGemiNumber($afm) === null) {
            throw new AfmNotFoundException("No GEMI number found for AFM [$afm].");
        }
    }
}
