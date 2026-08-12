<?php

namespace Sonole\GovGr\Tests;

use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovServiceException;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
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

        $result = Gemi::companyBoard('010540663');

        $this->assertSame('ΤΕΣΤ', $result['result']['board']['fp'][0]['lastname']);

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

    public function test_board_returns_the_full_fp_record_shape(): void
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

        $result = Gemi::companyBoard('010540663')['result'];

        $this->assertSame('ΠΡΟΕΔΡΟΣ', $result['board']['fp'][0]['relationDescr']);
        $this->assertSame('ΑΣΤΥΝΟΜΙΚΗ ΤΑΥΤΟΤΗΤΑ', $result['board']['fp'][0]['identityType']);
        $this->assertSame([], $result['board']['np']);
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

        $result = Gemi::basicInfo('010540663');

        $this->assertSame('ΤΕΣΤ ΑΕ', $result['result']['company']['businessName']);

        Http::assertSent(fn (ClientRequest $r) => $r->data()['getBasicInfoV3InputRecord']['afm'] === '010540663'
            && isset($r->data()['auditRecord']['auditProtocol']));
    }

    public function test_basic_info_returns_the_full_company_record_shape(): void
    {
        // Fake/placeholder company data below (not from a real call). Field names match a real response; values do not.
        Http::fake(['*' => Http::response([
            'getBasicInfoV3OutputRecord' => ['company' => [
                'arGemi' => '000000000000', 'afm' => '000000000', 'legalTypeId' => '1', 'legalTypeDescr' => 'ΑΕ',
                'memberStatusId' => '3', 'memberStatusDescr' => 'Ενεργή',
                'businessName' => 'ΔΕΙΓΜΑ ΑΝΩΝΥΜΗ ΕΤΑΙΡΕΙΑ', 'businessTitles' => ['ΔΕΙΓΜΑ ΑΕ'],
                'branch' => [],
                'businessApplications' => [[
                    'kak' => '000000', 'appSubjectId' => '1', 'appSubjectDescr' => 'ΔΕΙΓΜΑ',
                    'appDecisionStatusDescr' => 'ΚΑΤΑΧΩΡΙΣΗ', 'deletionSubject' => 'false',
                ]],
                'businessSubjects' => [[
                    'professionId' => '00000', 'professionCode' => '00000000', 'professionDescr' => 'ΔΕΙΓΜΑ', 'startDate' => '2020-01-01',
                ]],
                'capital' => [],
                'stocks' => [['amount' => '2500', 'stockTypeId' => '1', 'stockType' => 'Ονομαστικές, Κοινές']],
            ]],
        ], 200, ['Content-Type' => 'application/json'])]);

        $result = Gemi::basicInfo('010540663')['result'];

        $this->assertSame('ΔΕΙΓΜΑ ΑΝΩΝΥΜΗ ΕΤΑΙΡΕΙΑ', $result['company']['businessName']);
        $this->assertSame(['ΔΕΙΓΜΑ ΑΕ'], $result['company']['businessTitles']);
        $this->assertSame('ΔΕΙΓΜΑ', $result['company']['businessApplications'][0]['appSubjectDescr']);
        $this->assertSame('Ονομαστικές, Κοινές', $result['company']['stocks'][0]['stockType']);
    }

    public function test_balance_sheets_sends_afm_and_gemi_number(): void
    {
        Http::fake(['*' => Http::response([
            'companyBalanceSheetsOutputRecord' => ['companyBalancesheets' => [['year' => '2023']]],
        ], 200, ['Content-Type' => 'application/json'])]);

        $result = Gemi::balanceSheets('010540663', '123456789012');

        $this->assertSame('2023', $result['result']['companyBalancesheets'][0]['year']);

        Http::assertSent(fn (ClientRequest $r) => $r->data()['companyBalanceSheetsInputRecord']['afm'] === '010540663'
            && $r->data()['companyBalanceSheetsInputRecord']['id'] === '123456789012');
    }

    public function test_balance_sheets_returns_both_the_empty_and_populated_year_shapes(): void
    {
        Http::fake(['*' => Http::response([
            'companyBalanceSheetsOutputRecord' => ['companyBalancesheets' => [
                ['year' => '2011', 'files' => []],
                [
                    'year' => '2019', 'netTurnover' => '0.00', 'totalAssets' => '0.00', 'numStuff' => '3', 'isXbrl' => false,
                    'files' => [['fileId' => '000000', 'arGemi' => '123456789012', 'type' => 'balancesheet']],
                ],
            ]],
        ], 200, ['Content-Type' => 'application/json'])]);

        $result = Gemi::balanceSheets('010540663', '123456789012')['result'];

        $this->assertSame([], $result['companyBalancesheets'][0]['files']);
        $this->assertSame('3', $result['companyBalancesheets'][1]['numStuff']);
        $this->assertSame('balancesheet', $result['companyBalancesheets'][1]['files'][0]['type']);
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