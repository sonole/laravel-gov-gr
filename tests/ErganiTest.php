<?php

namespace Sonole\GovGr\Tests;

use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovServiceException;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Sonole\GovGr\Ergani\Facades\Ergani;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

class ErganiTest extends TestCase
{
    private const EMPLOYEE_RELATION_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:getEmployeeRelationResponse xmlns:ns2="http://gsis.ggps.interoperability/ErganiInterface">
              <getEmployeeRelationOutputRecord>
                <code>000</code>
                <message>Success</message>
                <doc>
                  <current>
                    <employerTaxNumber>099028220</employerTaxNumber>
                    <employerName>ΑΚΑΔΗΜΑΙΚΟ ΔΙΑΔΙΚΤΥΟ</employerName>
                    <employeeTaxNumber>161182011</employeeTaxNumber>
                    <employeeSalary>2600.00</employeeSalary>
                    <employeeHourSalary>15.60</employeeHourSalary>
                    <employeeWeekHours>40.0</employeeWeekHours>
                  </current>
                </doc>
              </getEmployeeRelationOutputRecord>
              <callSequenceId>1</callSequenceId>
              <callSequenceDate>2020-07-17T11:03:12.579+03:00</callSequenceDate>
              <errorRecord/>
            </ns2:getEmployeeRelationResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const EMPLOYMENT_RELATIONSHIP_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:getEmploymentRelationshipResponse xmlns:ns2="http://gsis.ggps.interoperability/ErganiInterface">
              <getEmploymentRelationshipOutputRecord>
                <code>000</code>
                <message>Success</message>
                <doc>
                  <sev>
                    <kind>1</kind>
                    <typos>0</typos>
                  </sev>
                </doc>
              </getEmploymentRelationshipOutputRecord>
              <callSequenceId>1</callSequenceId>
              <errorRecord/>
            </ns2:getEmploymentRelationshipResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const HISTORY_BY_PERIOD_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:getEmploymentHistoryByPeriodResponse xmlns:ns2="http://gsis.ggps.interoperability/ErganiInterface">
              <getEmploymentHistoryByPeriodOutputRecord>
                <code>000</code>
                <message>Success</message>
                <doc>
                  <hist>
                    <merTaxId>802660454</merTaxId>
                    <merName>WOW BURGERS HELLAS ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ</merName>
                    <aaMain>0</aaMain>
                    <stakodMainCode>5611</stakodMainCode>
                    <stakodMainDescr>Δραστηριότητες εστιατορίων</stakodMainDescr>
                    <addressMain>ΠΛΑΤΕΙΑ ΕΛΕΥΘΕΡΩΤΩΝ</addressMain>
                    <addressArithmosMain>11</addressArithmosMain>
                    <tkMain>15232</tkMain>
                    <kalDhmoiMainCode>9178</kalDhmoiMainCode>
                    <kalDhmoiMainDescr>ΧΑΛΑΝΔΡΙΟΥ</kalDhmoiMainDescr>
                    <nomMorfMainCode>007</nomMorfMainCode>
                    <nomMorfMainDescr>ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ (ΙΚΕ)</nomMorfMainDescr>
                    <aaMpa>0</aaMpa>
                    <stakodMpaCode>5611</stakodMpaCode>
                    <stakodMpaDescr>Δραστηριότητες εστιατορίων</stakodMpaDescr>
                    <addressMpa>ΠΛΑΤΕΙΑ ΕΛΕΥΘΕΡΩΤΩΝ</addressMpa>
                    <addressArithmosMpa>11</addressArithmosMpa>
                    <tkMpa>15232</tkMpa>
                    <kalDhmoiMpaCode>9178</kalDhmoiMpaCode>
                    <kalDhmoiMpaDescr>ΧΑΛΑΝΔΡΙΟΥ</kalDhmoiMpaDescr>
                    <step3Code>514</step3Code>
                    <step3Descr>ΜΑΓΕΙΡΟΙ</step3Descr>
                    <step2Code>51</step2Code>
                    <step2Descr>ΑΠΑΣΧΟΛΟΥΜΕΝΟΙ ΣΤΗΝ ΠΑΡΟΧΗ ΠΡΟΣΩΠΙΚΩΝ ΥΠΗΡΕΣΙΩΝ</step2Descr>
                    <step4Code>5140</step4Code>
                    <step4Descr>ΜΑΓΕΙΡΟΙ</step4Descr>
                    <step6Code>514002</step6Code>
                    <step6Descr>ΜΑΓΕΙΡΟΙ, ΓΕΝΙΚΑ</step6Descr>
                    <kathestws>0</kathestws>
                    <apodoxes>880.00</apodoxes>
                    <weekHours>40.0</weekHours>
                    <dateFrom>2025-11-24T00:00:00+02:00</dateFrom>
                    <dateTo>2025-11-27T17:12:00.85+02:00</dateTo>
                  </hist>
                  <hist>
                    <merTaxId>802660454</merTaxId>
                    <merName>WOW BURGERS HELLAS ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ</merName>
                    <aaMain>0</aaMain>
                    <stakodMainCode>5611</stakodMainCode>
                    <stakodMainDescr>Δραστηριότητες εστιατορίων</stakodMainDescr>
                    <addressMain>ΠΛΑΤΕΙΑ ΕΛΕΥΘΕΡΩΤΩΝ</addressMain>
                    <addressArithmosMain>11</addressArithmosMain>
                    <tkMain>15232</tkMain>
                    <kalDhmoiMainCode>9178</kalDhmoiMainCode>
                    <kalDhmoiMainDescr>ΧΑΛΑΝΔΡΙΟΥ</kalDhmoiMainDescr>
                    <nomMorfMainCode>007</nomMorfMainCode>
                    <nomMorfMainDescr>ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ (ΙΚΕ)</nomMorfMainDescr>
                    <aaMpa>0</aaMpa>
                    <stakodMpaCode>5611</stakodMpaCode>
                    <stakodMpaDescr>Δραστηριότητες εστιατορίων</stakodMpaDescr>
                    <addressMpa>ΠΛΑΤΕΙΑ ΕΛΕΥΘΕΡΩΤΩΝ</addressMpa>
                    <addressArithmosMpa>11</addressArithmosMpa>
                    <tkMpa>15232</tkMpa>
                    <kalDhmoiMpaCode>9178</kalDhmoiMpaCode>
                    <kalDhmoiMpaDescr>ΧΑΛΑΝΔΡΙΟΥ</kalDhmoiMpaDescr>
                    <step3Code>514</step3Code>
                    <step3Descr>ΜΑΓΕΙΡΟΙ</step3Descr>
                    <step2Code>51</step2Code>
                    <step2Descr>ΑΠΑΣΧΟΛΟΥΜΕΝΟΙ ΣΤΗΝ ΠΑΡΟΧΗ ΠΡΟΣΩΠΙΚΩΝ ΥΠΗΡΕΣΙΩΝ</step2Descr>
                    <step4Code>5140</step4Code>
                    <step4Descr>ΜΑΓΕΙΡΟΙ</step4Descr>
                    <step6Code>514002</step6Code>
                    <step6Descr>ΜΑΓΕΙΡΟΙ, ΓΕΝΙΚΑ</step6Descr>
                    <kathestws>0</kathestws>
                    <apodoxes>880.00</apodoxes>
                    <weekHours>40.0</weekHours>
                    <dateFrom>2025-11-28T17:12:00.85+02:00</dateFrom>
                    <dateTo>2026-04-07T00:00:00+03:00</dateTo>
                  </hist>
                </doc>
              </getEmploymentHistoryByPeriodOutputRecord>
              <callSequenceId>1</callSequenceId>
              <errorRecord/>
            </ns2:getEmploymentHistoryByPeriodResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const BUSINESS_ERROR_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:getEmployeeRelationResponse xmlns:ns2="http://gsis.ggps.interoperability/ErganiInterface">
              <getEmployeeRelationOutputRecord>
                <code>001</code>
                <message>Δεν βρέθηκαν στοιχεία απασχόλησης</message>
              </getEmployeeRelationOutputRecord>
              <callSequenceId>1</callSequenceId>
              <errorRecord/>
            </ns2:getEmployeeRelationResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const GENERAL_ERROR_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:getEmployeeRelationResponse xmlns:ns2="http://gsis.ggps.interoperability/ErganiInterface">
              <callSequenceId>1</callSequenceId>
              <errorRecord>
                <errorCode>GEN_INVALID_DATA</errorCode>
                <errorDescr>Κάποιο υποχρεωτικό πεδίο δεν δόθηκε</errorDescr>
              </errorRecord>
            </ns2:getEmployeeRelationResponse>
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

    public function test_employee_relation_returns_the_doc_contents(): void
    {
        Http::fake(['*' => Http::response(self::EMPLOYEE_RELATION_XML, 200, ['Content-Type' => 'text/xml'])]);

        $result = Ergani::employeeRelation('161182011');

        $this->assertSame('ΑΚΑΔΗΜΑΙΚΟ ΔΙΑΔΙΚΤΥΟ', $result['result']['current']['employerName']);
        $this->assertSame('2600.00', $result['result']['current']['employeeSalary']);
        $this->assertSame('15.60', $result['result']['current']['employeeHourSalary']);
        $this->assertSame('40.0', $result['result']['current']['employeeWeekHours']);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<afm>161182011</afm>')
            && str_contains($r->body(), '<auditUserId>system@example.com</auditUserId>'));
    }

    public function test_employment_relationship_sends_refdate_before_afm(): void
    {
        Http::fake(['*' => Http::response(self::EMPLOYMENT_RELATIONSHIP_XML, 200, ['Content-Type' => 'text/xml'])]);

        $result = Ergani::employmentRelationship('161182011', '03/12/2020');

        $this->assertSame('1', $result['result']['sev']['kind']);
        $this->assertSame('0', $result['result']['sev']['typos']);

        Http::assertSent(function (ClientRequest $r) {
            $refdatePos = strpos($r->body(), '<refdate>03/12/2020</refdate>');
            $afmPos = strpos($r->body(), '<afm>161182011</afm>');

            return $refdatePos !== false && $afmPos !== false && $refdatePos < $afmPos;
        });
    }

    public function test_employment_history_by_period_normalizes_multiple_hist_entries(): void
    {
        Http::fake(['*' => Http::response(self::HISTORY_BY_PERIOD_XML, 200, ['Content-Type' => 'text/xml'])]);

        $result = Ergani::employmentHistoryByPeriod('116627107', '03/12/2024', '01/03/2026');

        $this->assertCount(2, $result['result']['hist']);
        $this->assertSame('Δραστηριότητες εστιατορίων', $result['result']['hist'][0]['stakodMainDescr']);
        $this->assertSame('880.00', $result['result']['hist'][0]['apodoxes']);

        Http::assertSent(function (ClientRequest $r) {
            $afmPos = strpos($r->body(), '<afm>116627107</afm>');
            $fromPos = strpos($r->body(), '<refdateFrom>03/12/2024</refdateFrom>');
            $toPos = strpos($r->body(), '<refdateTo>01/03/2026</refdateTo>');

            return $afmPos !== false && $fromPos !== false && $toPos !== false
                && $afmPos < $fromPos && $fromPos < $toPos;
        });
    }

    public function test_a_business_error_code_throws(): void
    {
        Http::fake(['*' => Http::response(self::BUSINESS_ERROR_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovServiceException::class);
        $this->expectExceptionMessage('Δεν βρέθηκαν στοιχεία απασχόλησης');

        Ergani::employeeRelation('987654321');
    }

    public function test_a_general_ked_error_throws(): void
    {
        Http::fake(['*' => Http::response(self::GENERAL_ERROR_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovServiceException::class);
        $this->expectExceptionMessage('Κάποιο υποχρεωτικό πεδίο δεν δόθηκε');

        Ergani::employeeRelation('987654321');
    }

    public function test_a_soap_fault_throws_an_authentication_exception(): void
    {
        Http::fake(['*' => Http::response(self::FAULT_XML, 500, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovAuthenticationException::class);

        Ergani::employeeRelation('987654321');
    }

    public function test_a_non_xml_error_page_throws_an_ergani_exception(): void
    {
        $forbidden = <<<'HTML'
            <!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
            <html><head>
            <title>403 Forbidden</title>
            </head><body>
            <h1>Forbidden</h1>
            <p>You are not authorized to access the requested resource.</p>
            </body></html>
            HTML;

        Http::fake(['*' => Http::response($forbidden, 403, ['Content-Type' => 'text/html'])]);

        $this->expectException(GovServiceException::class);

        Ergani::employeeRelation('987654321');
    }

    public function test_empty_credentials_are_rejected_before_any_http_call_or_audit_write(): void
    {
        Http::fake(['*' => Http::response(self::EMPLOYEE_RELATION_XML, 200, ['Content-Type' => 'text/xml'])]);
        config(['govgr.ergani.pilot.password' => '']);

        try {
            Ergani::employeeRelation('987654321');
            $this->fail('Expected an GovAuthenticationException.');
        } catch (GovAuthenticationException $e) {
            $this->assertStringContainsString('ERGANI_PILOT', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }
}