<?php

namespace Sonole\GovGr\Tests;

use Sonole\GovGr\BankConfirm\Facades\BankConfirm;
use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovServiceException;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

class BankConfirmTest extends TestCase
{
    private const CONFIRMED_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:confirmAccountResponse xmlns:ns2="http://gsis.ggps.interoperability/confirmBankInterface">
              <confirmAccountOutputRecord>
                <bank>ΤΡΑΠΕΖΑ Α</bank>
                <confirmed>true</confirmed>
                <usageAllowed>true</usageAllowed>
                <validityTimestamp>2026-08-09T10:00:00</validityTimestamp>
              </confirmAccountOutputRecord>
              <callSequenceId>1</callSequenceId>
              <errorRecord/>
            </ns2:confirmAccountResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const NOT_CONFIRMED_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:confirmAccountResponse xmlns:ns2="http://gsis.ggps.interoperability/confirmBankInterface">
              <confirmAccountOutputRecord>
                <confirmed>false</confirmed>
                <errorCode>IBAN_AFM_MISMATCH</errorCode>
              </confirmAccountOutputRecord>
              <callSequenceId>1</callSequenceId>
              <errorRecord/>
            </ns2:confirmAccountResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const GENERAL_ERROR_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:confirmAccountResponse xmlns:ns2="http://gsis.ggps.interoperability/confirmBankInterface">
              <callSequenceId>1</callSequenceId>
              <errorRecord>
                <errorCode>WS_EXTERNAL_ERROR</errorCode>
                <errorDescr>Ο ΑΦΜ ΔΕΝ ΒΡΕΘΗΚΕ</errorDescr>
              </errorRecord>
            </ns2:confirmAccountResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const FAULT_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <soap:Fault>
              <faultcode xmlns:ns1="http://ws.apache.org/wss4j">ns1:SecurityError</faultcode>
              <faultstring>A security error was encountered when verifying the message</faultstring>
            </soap:Fault>
          </soap:Body>
        </soap:Envelope>
        XML;

    public function test_confirm_account_returns_the_output_record(): void
    {
        Http::fake(['*' => Http::response(self::CONFIRMED_XML, 200, ['Content-Type' => 'text/xml'])]);

        $result = BankConfirm::confirmAccount('GR1601101250000000012300695', '123456789');

        $this->assertSame('true', $result['result']['confirmed']);
        $this->assertSame('ΤΡΑΠΕΖΑ Α', $result['result']['bank']);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<iban>GR1601101250000000012300695</iban>')
            && str_contains($r->body(), '<afm>123456789</afm>')
            && str_contains($r->body(), '<auditUserId>system@example.com</auditUserId>'));
    }

    public function test_a_populated_output_error_code_throws(): void
    {
        Http::fake(['*' => Http::response(self::NOT_CONFIRMED_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovServiceException::class);
        $this->expectExceptionMessage('IBAN_AFM_MISMATCH');

        BankConfirm::confirmAccount('GR1601101250000000012300695', '123456789');
    }

    public function test_a_populated_general_error_record_throws(): void
    {
        Http::fake(['*' => Http::response(self::GENERAL_ERROR_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovServiceException::class);
        $this->expectExceptionMessage('Ο ΑΦΜ ΔΕΝ ΒΡΕΘΗΚΕ');

        BankConfirm::confirmAccount('GR1601101250000000012300695', '123456789');
    }

    public function test_a_soap_fault_throws_an_authentication_exception(): void
    {
        Http::fake(['*' => Http::response(self::FAULT_XML, 500, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovAuthenticationException::class);

        BankConfirm::confirmAccount('GR1601101250000000012300695', '123456789');
    }

    public function test_empty_credentials_are_rejected_before_any_http_call_or_audit_write(): void
    {
        Http::fake(['*' => Http::response(self::CONFIRMED_XML, 200, ['Content-Type' => 'text/xml'])]);
        config(['govgr.bank-confirm.pilot.password' => '']);

        try {
            BankConfirm::confirmAccount('GR1601101250000000012300695', '123456789');
            $this->fail('Expected a GovAuthenticationException.');
        } catch (GovAuthenticationException $e) {
            $this->assertStringContainsString('BANK_CONFIRM_PILOT', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }
}