<?php

namespace Sonole\GovGr\Tests;

use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovNotFoundException;
use Sonole\GovGr\Core\Exceptions\GovServiceException;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Sonole\GovGr\Gemi\Data\BranchType;
use Sonole\GovGr\Gemi\Exceptions\AfmNotFoundException;
use Sonole\GovGr\Gemi\Facades\Gemi;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

class GemiTest extends TestCase
{
    private const VALIDATION_SUCCESS_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:checkAfmExistsResponse xmlns:ns2="http://gsis.ggps.interoperability/gemiValidationInterface">
              <checkAfmExistsOutputRecord>
                <companies>
                  <company>
                    <gemiNumber>123456789012</gemiNumber>
                    <responsibleOrg>2</responsibleOrg>
                    <isBranch>false</isBranch>
                    <legalTypeId>1</legalTypeId>
                  </company>
                </companies>
              </checkAfmExistsOutputRecord>
              <callSequenceId>1</callSequenceId>
              <errorRecord/>
            </ns2:checkAfmExistsResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const VALIDATION_MULTI_COMPANY_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:checkAfmExistsResponse xmlns:ns2="http://gsis.ggps.interoperability/gemiValidationInterface">
              <checkAfmExistsOutputRecord>
                <companies>
                  <company>
                    <gemiNumber>111111111111</gemiNumber>
                    <isBranch>true</isBranch>
                  </company>
                  <company>
                    <gemiNumber>222222222222</gemiNumber>
                    <isBranch>false</isBranch>
                  </company>
                </companies>
              </checkAfmExistsOutputRecord>
              <callSequenceId>1</callSequenceId>
              <errorRecord/>
            </ns2:checkAfmExistsResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const VALIDATION_NO_COMPANIES_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:checkAfmExistsResponse xmlns:ns2="http://gsis.ggps.interoperability/gemiValidationInterface">
              <checkAfmExistsOutputRecord>
                <companies></companies>
              </checkAfmExistsOutputRecord>
              <errorRecord/>
            </ns2:checkAfmExistsResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const VALIDATION_FAULT_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <soap:Fault>
              <faultcode xmlns:ns1="http://ws.apache.org/wss4j">ns1:SecurityError</faultcode>
              <faultstring>A security error was encountered when verifying the message</faultstring>
            </soap:Fault>
          </soap:Body>
        </soap:Envelope>
        XML;

    private function fakeValidationThenBoard(array $boardResponse): void
    {
        Http::fake([
            'pilot.test/gemiValidationService*' => Http::response(self::VALIDATION_SUCCESS_XML, 200, ['Content-Type' => 'text/xml']),
            'pilot.test/gemiBoardRestService*' => Http::response($boardResponse, 200, ['Content-Type' => 'application/json']),
        ]);
    }

    public function test_validate_resolves_the_gemi_number_from_a_nested_soap_response(): void
    {
        Http::fake(['*' => Http::response(self::VALIDATION_SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);

        $result = Gemi::validate('010540663');

        $this->assertSame('123456789012', $result['result']['companies']['company']['gemiNumber']);
        $this->assertSame('2', $result['result']['companies']['company']['responsibleOrg']);
        $this->assertSame('1', $result['result']['companies']['company']['legalTypeId']);
    }

    public function test_validation_soap_fault_throws_authentication_exception(): void
    {
        Http::fake(['*' => Http::response(self::VALIDATION_FAULT_XML, 500, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovAuthenticationException::class);

        Gemi::validate('010540663');
    }

    public function test_get_gemi_number_returns_the_first_non_branch_companys_number(): void
    {
        Http::fake(['*' => Http::response(self::VALIDATION_SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->assertSame('123456789012', Gemi::getGemiNumber('010540663'));
    }

    public function test_get_gemi_number_skips_branch_companies(): void
    {
        Http::fake(['*' => Http::response(self::VALIDATION_MULTI_COMPANY_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->assertSame('222222222222', Gemi::getGemiNumber('010540663'));
    }

    public function test_get_gemi_numbers_returns_every_companys_number_branches_included(): void
    {
        Http::fake(['*' => Http::response(self::VALIDATION_MULTI_COMPANY_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->assertSame(['111111111111', '222222222222'], Gemi::getGemiNumbers('010540663'));
    }

    public function test_get_gemi_number_returns_null_instead_of_throwing_when_afm_has_no_companies(): void
    {
        Http::fake(['*' => Http::response(self::VALIDATION_NO_COMPANIES_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->assertNull(Gemi::getGemiNumber('999999999'));
        $this->assertSame([], Gemi::getGemiNumbers('999999999'));
    }

    public function test_board_resolves_gemi_number_then_sends_it_with_the_audit_record(): void
    {
        $this->fakeValidationThenBoard([
            'getCompanyBoardV3OutputRecord' => [
                'board' => ['fp' => [['afm' => '123456789', 'lastname' => 'ΤΕΣΤ']]],
            ],
        ]);

        $board = Gemi::companyBoard('010540663');

        $this->assertSame('ΤΕΣΤ', $board->members[0]->surname);
        $this->assertSame('123456789', $board->members[0]->afm);
        $this->assertNotNull($board->auditRecord);

        Http::assertSent(function (ClientRequest $r) {
            if (! str_contains($r->url(), 'gemiBoardRestService')) {
                return true;
            }

            $body = $r->data();

            return $body['getCompanyBoardV3InputRecord']['afm'] === '010540663'
                && $body['getCompanyBoardV3InputRecord']['arGemi'] === '123456789012'
                && $body['auditRecord']['auditUnit'] === 'TEST'
                && $body['auditRecord']['auditUserId'] === 'system@example.com'
                && isset($body['auditRecord']['auditProtocol'], $body['auditRecord']['auditTransactionId']);
        });
    }

    public function test_board_maps_members_and_keeps_the_raw_record(): void
    {
        // Fake/placeholder personal data below (not from a real call). Field names match a real response; values do not.
        $this->fakeValidationThenBoard([
            'getCompanyBoardV3OutputRecord' => [
                'board' => [
                    'fp' => [[
                        'afm' => '000000000', 'lastname' => 'ΠΑΠΑΔΟΠΟΥΛΟΣ', 'firstname' => 'ΓΙΑΝΝΗΣ',
                        'homeCity' => 'ΑΘΗΝΑ', 'homeStreet' => 'ΟΔΟΣ ΔΕΙΓΜΑ', 'homeNumber' => '1', 'zipCode' => '10000',
                        'isRepresentativeAlone' => 'false', 'isRepresentativeInCommon' => 'false',
                        'relationDescr' => 'ΠΡΟΕΔΡΟΣ', 'relationId' => '1', 'relationType' => 'BOARD',
                        'codeFP' => '000000', 'fathersName' => 'ΠΕΤΡΟΣ', 'mothersName' => 'ΜΑΡΙΑ',
                        'activeMember' => 'true', 'electionDate' => '2020-01-01', 'startDate' => '2020-01-01',
                        'endDate' => '2025-01-01', 'identityNumber' => 'ΑΒ000000', 'identityType' => 'ΑΣΤΥΝΟΜΙΚΗ ΤΑΥΤΟΤΗΤΑ',
                    ]],
                    'np' => [],
                ],
            ],
        ]);

        $board = Gemi::companyBoard('010540663');
        $member = $board->members[0];

        $this->assertSame('ΠΡΟΕΔΡΟΣ', $member->relationDescr);
        $this->assertSame('ΑΣΤΥΝΟΜΙΚΗ ΤΑΥΤΟΤΗΤΑ', $member->idType);
        $this->assertSame('ΑΒ000000', $member->idNumber);
        $this->assertSame('ΓΙΑΝΝΗΣ', $member->firstName);
        $this->assertSame('ΟΔΟΣ ΔΕΙΓΜΑ', $member->address->street);
        $this->assertSame('10000', $member->address->zip);
        $this->assertTrue($member->isActive);
        $this->assertFalse($member->isRepresentative());
        $this->assertSame('2020-01-01', $member->electionDate?->format('Y-m-d'));
        $this->assertSame('2025-01-01', $member->endDate?->format('Y-m-d'));
        $this->assertSame('BOARD', $member->raw['relationType']);
        $this->assertSame([], $board->entities);
        $this->assertSame([$member], $board->activeMembers());
        $this->assertSame([], $board->representatives());
        $this->assertFalse($board->isEmpty());
    }

    public function test_board_maps_legal_persons_and_representatives(): void
    {
        $this->fakeValidationThenBoard([
            'getCompanyBoardV3OutputRecord' => [
                'board' => [
                    'fp' => [[
                        'afm' => '000000000', 'lastname' => 'ΔΕΙΓΜΑ', 'firstname' => 'ΔΕΙΓΜΑ', 'percentage' => '50',
                        'isRepresentativeAlone' => 'true', 'isRepresentativeInCommon' => 'false', 'activeMember' => 'true',
                    ]],
                    'np' => [[
                        'codeNP' => '1', 'afm' => '000000000', 'arGemi' => '000000000000', 'businessName' => 'ΔΕΙΓΜΑ ΕΠΕ',
                        'legalTypeId' => '2', 'legalTypeDescr' => 'ΕΠΕ', 'relationDescr' => 'Εταίρος', 'activeMember' => 'true',
                        'isRepresentativeAlone' => 'false', 'isRepresentativeInCommon' => 'true', 'percentage' => '50',
                        'street' => 'ΟΔΟΣ', 'streetNumber' => '2', 'zipCode' => '10000', 'city' => 'ΑΘΗΝΑ',
                    ]],
                ],
            ],
        ]);

        $board = Gemi::companyBoard('010540663');

        $this->assertSame(50.0, $board->members[0]->percentage);
        $this->assertTrue($board->members[0]->isRepresentativeAlone);
        $this->assertCount(1, $board->representatives());
        $this->assertSame('ΔΕΙΓΜΑ ΕΠΕ', $board->entities[0]->name);
        $this->assertSame('000000000000', $board->entities[0]->gemiNumber);
        $this->assertTrue($board->entities[0]->isRepresentativeInCommon);
        $this->assertTrue($board->entities[0]->isRepresentative());
        $this->assertSame('ΑΘΗΝΑ', $board->entities[0]->address->area);
    }

    public function test_board_is_empty_when_the_registry_sends_no_lists(): void
    {
        $this->fakeValidationThenBoard(['getCompanyBoardV3OutputRecord' => ['board' => []]]);

        $board = Gemi::companyBoard('010540663');

        $this->assertTrue($board->isEmpty());
        $this->assertSame([], $board->members);
    }

    public function test_board_throws_afm_not_found_when_validation_returns_no_companies(): void
    {
        Http::fake(['*' => Http::response(<<<'XML'
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <ns2:checkAfmExistsResponse xmlns:ns2="http://gsis.ggps.interoperability/gemiValidationInterface">
                  <checkAfmExistsOutputRecord>
                    <companies></companies>
                  </checkAfmExistsOutputRecord>
                  <errorRecord/>
                </ns2:checkAfmExistsResponse>
              </soap:Body>
            </soap:Envelope>
            XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->expectException(AfmNotFoundException::class);

        Gemi::companyBoard('999999999');
    }

    public function test_rest_authentication_failure_throws_authentication_exception_despite_200_status(): void
    {
        Http::fake(['*' => Http::response([
            'errorRecord' => ['errorCode' => 'GEN_AUTHENTICATION_FAILED', 'errorDescr' => 'Η αυθεντικοποίηση χρήστη απέτυχε'],
        ], 200, ['Content-Type' => 'application/json'])]);

        $this->expectException(GovAuthenticationException::class);
        $this->expectExceptionMessage('Η αυθεντικοποίηση χρήστη απέτυχε');

        Gemi::basicInfo('010540663');
    }

    public function test_rest_business_error_throws_gemi_exception(): void
    {
        Http::fake(['*' => Http::response([
            'getBasicInfoV3OutputRecord' => ['errorCode' => '404', 'errorDescr' => 'Δεν βρέθηκε επιχείρηση'],
        ], 200, ['Content-Type' => 'application/json'])]);

        $this->expectException(GovServiceException::class);
        $this->expectExceptionMessage('Δεν βρέθηκε επιχείρηση');

        Gemi::basicInfo('010540663');
    }

    public function test_basic_info_sends_the_audit_record_and_returns_the_output_record(): void
    {
        Http::fake(['*' => Http::response([
            'getBasicInfoV3OutputRecord' => ['company' => ['businessName' => 'ΤΕΣΤ ΑΕ']],
        ], 200, ['Content-Type' => 'application/json'])]);

        $company = Gemi::basicInfo('010540663');

        $this->assertSame('ΤΕΣΤ ΑΕ', $company->name);
        $this->assertNotNull($company->auditRecord);

        Http::assertSent(fn (ClientRequest $r) => $r->data()['getBasicInfoV3InputRecord']['afm'] === '010540663'
            && isset($r->data()['auditRecord']['auditProtocol']));
    }

    public function test_basic_info_throws_not_found_when_the_registry_sends_no_company(): void
    {
        Http::fake(['*' => Http::response([
            'getBasicInfoV3OutputRecord' => [],
        ], 200, ['Content-Type' => 'application/json'])]);

        $this->expectException(GovNotFoundException::class);

        Gemi::basicInfo('010540663');
    }

    public function test_basic_info_maps_the_company_record(): void
    {
        // Fake/placeholder company data below (not from a real call). Field names match a real response; values do not.
        Http::fake(['*' => Http::response([
            'getBasicInfoV3OutputRecord' => ['company' => [
                'arGemi' => '000000000000', 'afm' => '000000000', 'legalTypeId' => '1', 'legalTypeDescr' => 'ΑΕ',
                'memberStatusId' => '3', 'memberStatusDescr' => 'Ενεργή', 'doyId' => '1190', 'doyDescr' => 'ΚΕΦΟΔΕ ΑΤΤΙΚΗΣ',
                'businessName' => 'ΔΕΙΓΜΑ ΑΝΩΝΥΜΗ ΕΤΑΙΡΕΙΑ', 'businessTitles' => ['ΔΕΙΓΜΑ ΑΕ'],
                'street' => 'ΟΔΟΣ ΔΕΙΓΜΑ', 'streetNumber' => '1', 'tk' => '10000', 'city' => 'ΑΘΗΝΑ', 'region' => 'ΑΘΗΝΑ',
                'email' => 'example@example.com', 'dateGemiRegistered' => '2017-05-16', 'dateIncorporated' => '2017-05-16',
                'duration' => '20', 'expirationDate' => '2037-05-16',
                'branch' => ['000000000001'],
                'businessApplications' => [[
                    'kak' => '000000', 'appSubjectId' => '1', 'appSubjectDescr' => 'ΔΕΙΓΜΑ', 'appDate' => '2017-06-14',
                    'appDecisionStatusDescr' => 'ΚΑΤΑΧΩΡΙΣΗ', 'deletionSubject' => 'false',
                    'url' => 'https://www.businessregistry.gr/downloadFile/index?key=assemblyDecision&elementId=000000',
                ]],
                'businessSubjects' => [
                    ['professionId' => '00000', 'professionCode' => '62010000', 'professionDescr' => 'ΔΕΙΓΜΑ', 'startDate' => '2020-01-01'],
                    ['professionId' => '00001', 'professionCode' => '62020000', 'professionDescr' => 'ΠΑΛΙΟ', 'startDate' => '2020-01-01', 'endDate' => '2021-01-01'],
                ],
                'capital' => ['capitalStock' => '5000', 'currency' => 'Euro', 'ecsokefalaiikes' => '25000'],
                'stocks' => [['amount' => '2500', 'stockTypeId' => '1', 'stockType' => 'Ονομαστικές, Κοινές']],
            ]],
        ], 200, ['Content-Type' => 'application/json'])]);

        $company = Gemi::basicInfo('010540663');

        $this->assertSame('000000000000', $company->gemiNumber);
        $this->assertSame('ΔΕΙΓΜΑ ΑΝΩΝΥΜΗ ΕΤΑΙΡΕΙΑ', $company->name);
        $this->assertSame(['ΔΕΙΓΜΑ ΑΕ'], $company->titles);
        $this->assertSame('ΑΕ', $company->legalTypeDescr);
        $this->assertSame('Ενεργή', $company->statusDescr);
        $this->assertSame('1190', $company->doy);
        $this->assertSame('ΟΔΟΣ ΔΕΙΓΜΑ', $company->address->street);
        $this->assertSame('1', $company->address->number);
        $this->assertSame('10000', $company->address->zip);
        $this->assertSame('ΑΘΗΝΑ', $company->address->area);
        $this->assertSame('2017-05-16', $company->gemiRegisteredAt?->format('Y-m-d'));
        $this->assertSame('2037-05-16', $company->expiresAt?->format('Y-m-d'));
        $this->assertNull($company->branchType);
        $this->assertFalse($company->isBranch());
        $this->assertSame(['000000000001'], $company->branches);
        $this->assertSame('ΔΕΙΓΜΑ', $company->applications[0]->subjectDescr);
        $this->assertSame('2017-06-14', $company->applications[0]->date?->format('Y-m-d'));
        $this->assertFalse($company->applications[0]->isDeletion);
        $this->assertFalse($company->isDeleted());
        $this->assertCount(2, $company->activities);
        $this->assertSame('62010000', $company->activities[0]->code);
        $this->assertTrue($company->activities[0]->isActive());
        $this->assertFalse($company->activities[1]->isActive());
        $this->assertCount(1, $company->activeActivities());
        $this->assertSame(5000.0, $company->capital?->amount);
        $this->assertSame(25000.0, $company->capital?->nonCapitalContributions);
        $this->assertNull($company->capital?->guaranteeContributions);
        $this->assertSame('Ονομαστικές, Κοινές', $company->stocks[0]->type);
        $this->assertSame(2500.0, $company->stocks[0]->amount);
        $this->assertSame('000000', $company->raw['businessApplications'][0]['kak']);
    }

    public function test_basic_info_maps_a_deleted_branch_with_an_empty_capital(): void
    {
        Http::fake(['*' => Http::response([
            'getBasicInfoV3OutputRecord' => ['company' => [
                'arGemi' => '001963839001', 'afm' => '000000000', 'businessName' => '(ΔΙΑΓΡΑΦΗΚΕ) ΔΕΙΓΜΑ',
                'memberStatusId' => '17', 'memberStatusDescr' => 'Διαγραφή',
                'branchType' => ['branchTypeId' => '1', 'branchTypeDescr' => 'Υποκατάστημα Ελληνικής Επιχείρησης'],
                'businessApplications' => [[
                    'kak' => '813001', 'appSubjectId' => '36', 'appSubjectDescr' => 'Λύση και εκκαθάριση εταιρίας, διαγραφή από ΓΕΜΗ',
                    'deletionSubject' => 'true',
                ]],
                'businessSubjects' => [],
                'capital' => [],
                'stocks' => [],
            ]],
        ], 200, ['Content-Type' => 'application/json'])]);

        $company = Gemi::basicInfo('010540663');

        $this->assertSame(BranchType::GreekCompany, $company->branchType);
        $this->assertTrue($company->isBranch());
        $this->assertTrue($company->isDeleted());
        $this->assertNull($company->capital);
        $this->assertSame([], $company->stocks);
        $this->assertSame([], $company->titles);
    }

    public function test_balance_sheets_sends_afm_and_gemi_number(): void
    {
        Http::fake(['*' => Http::response([
            'companyBalanceSheetsOutputRecord' => ['companyBalancesheets' => [['year' => '2023']]],
        ], 200, ['Content-Type' => 'application/json'])]);

        $sheets = Gemi::balanceSheets('010540663', '123456789012');

        $this->assertCount(1, $sheets);
        $this->assertSame('2023', $sheets[0]->year);
        $this->assertNotNull($sheets[0]->auditRecord);

        Http::assertSent(fn (ClientRequest $r) => $r->data()['companyBalanceSheetsInputRecord']['afm'] === '010540663'
            && $r->data()['companyBalanceSheetsInputRecord']['id'] === '123456789012');
    }

    public function test_balance_sheets_maps_both_the_empty_and_populated_year_shapes(): void
    {
        Http::fake(['*' => Http::response([
            'companyBalanceSheetsOutputRecord' => ['companyBalancesheets' => [
                ['year' => 'Από 1/1 έως 31/12', 'isXbrl' => false, 'files' => []],
                [
                    'year' => '2019', 'netTurnover' => '12345', 'totalAssets' => '0.00', 'numStuff' => '3',
                    'accountsReceivable' => '9696', 'preTaxResults' => '-11788', 'isXbrl' => true,
                    'ixbrlUrl' => 'https://example.test/ixbrlview.html',
                    'files' => [['fileId' => '000000', 'arGemi' => '123456789012', 'type' => 'balancesheet']],
                ],
            ]],
        ], 200, ['Content-Type' => 'application/json'])]);

        $sheets = Gemi::balanceSheets('010540663', '123456789012');

        $this->assertSame('Από 1/1 έως 31/12', $sheets[0]->year);
        $this->assertFalse($sheets[0]->hasFiles());
        $this->assertNull($sheets[0]->netTurnover);
        $this->assertFalse($sheets[0]->isXbrl);
        $this->assertSame(3.0, $sheets[1]->averageEmployees);
        $this->assertSame(12345.0, $sheets[1]->netTurnover);
        $this->assertSame(0.0, $sheets[1]->totalAssets);
        $this->assertSame(-11788.0, $sheets[1]->preTaxResults);
        $this->assertNull($sheets[1]->inventories);
        $this->assertTrue($sheets[1]->isXbrl);
        $this->assertSame('https://example.test/ixbrlview.html', $sheets[1]->ixbrlUrl);
        $this->assertTrue($sheets[1]->hasFiles());
        $this->assertSame('000000', $sheets[1]->files[0]->id);
        $this->assertSame('balancesheet', $sheets[1]->files[0]->type);
        $this->assertSame('3', $sheets[1]->raw['numStuff']);
    }

    public function test_balance_sheets_is_an_empty_list_when_nothing_is_filed(): void
    {
        Http::fake(['*' => Http::response([
            'companyBalanceSheetsOutputRecord' => ['companyBalancesheets' => []],
        ], 200, ['Content-Type' => 'application/json'])]);

        $this->assertSame([], Gemi::balanceSheets('010540663', '123456789012'));
    }

    public function test_empty_credentials_are_rejected_before_any_http_call_or_audit_write(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        config(['govgr.gemi.basic_info.pilot.password' => '']);

        try {
            Gemi::basicInfo('010540663');
            $this->fail('Expected a GovAuthenticationException.');
        } catch (GovAuthenticationException $e) {
            $this->assertStringContainsString('GEMI_BASIC_INFO_PILOT', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }
}