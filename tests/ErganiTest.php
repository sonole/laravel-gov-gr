<?php

namespace Sonole\GovGr\Tests;

use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovServiceException;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Sonole\GovGr\Ergani\Data\EmploymentKind;
use Sonole\GovGr\Ergani\Data\EmploymentType;
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

    public function test_employee_relation_maps_the_current_record(): void
    {
        Http::fake(['*' => Http::response(self::EMPLOYEE_RELATION_XML, 200, ['Content-Type' => 'text/xml'])]);

        $relation = Ergani::employeeRelation('161182011');

        $this->assertNotNull($relation);
        $this->assertSame('099028220', $relation->employerAfm);
        $this->assertSame('ΑΚΑΔΗΜΑΙΚΟ ΔΙΑΔΙΚΤΥΟ', $relation->employerName);
        $this->assertSame('161182011', $relation->employeeAfm);
        $this->assertSame(2600.0, $relation->monthlySalary);
        $this->assertSame(15.6, $relation->hourlyWage);
        $this->assertSame(40.0, $relation->weeklyHours);
        $this->assertSame('2600.00', $relation->raw['employeeSalary']);
        $this->assertNotNull($relation->auditRecord);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<afm>161182011</afm>')
            && str_contains($r->body(), '<auditUserId>system@example.com</auditUserId>'));
    }

    public function test_employee_relation_is_null_without_a_current_record(): void
    {
        Http::fake(['*' => Http::response(str_replace(
            ['<current>', '</current>'], ['<!--', '-->'], self::EMPLOYEE_RELATION_XML,
        ), 200, ['Content-Type' => 'text/xml'])]);

        $this->assertNull(Ergani::employeeRelation('161182011'));
    }

    public function test_employment_relationship_sends_refdate_before_afm(): void
    {
        Http::fake(['*' => Http::response(self::EMPLOYMENT_RELATIONSHIP_XML, 200, ['Content-Type' => 'text/xml'])]);

        $status = Ergani::employmentRelationship('161182011', '03/12/2020');

        $this->assertSame(EmploymentKind::FixedTerm, $status->kind);
        $this->assertSame(EmploymentType::FullTime, $status->type);
        $this->assertSame('1', $status->raw['kind']);
        $this->assertNotNull($status->auditRecord);

        Http::assertSent(function (ClientRequest $r) {
            $refdatePos = strpos($r->body(), '<refdate>03/12/2020</refdate>');
            $afmPos = strpos($r->body(), '<afm>161182011</afm>');

            return $refdatePos !== false && $afmPos !== false && $refdatePos < $afmPos;
        });
    }

    public function test_employment_relationship_leaves_an_undocumented_code_null(): void
    {
        Http::fake(['*' => Http::response(str_replace(
            ['<kind>1</kind>', '<typos>0</typos>'], ['<kind>7</kind>', '<typos>-1</typos>'], self::EMPLOYMENT_RELATIONSHIP_XML,
        ), 200, ['Content-Type' => 'text/xml'])]);

        $status = Ergani::employmentRelationship('161182011', '03/12/2020');

        $this->assertNull($status->kind);
        $this->assertSame(EmploymentType::Unknown, $status->type);
        $this->assertSame('7', $status->raw['kind']);
    }

    public function test_employment_history_by_period_maps_every_hist_entry(): void
    {
        Http::fake(['*' => Http::response(self::HISTORY_BY_PERIOD_XML, 200, ['Content-Type' => 'text/xml'])]);

        $history = Ergani::employmentHistoryByPeriod('116627107', '03/12/2024', '01/03/2026');

        $this->assertCount(2, $history);
        $entry = $history[0];
        $this->assertSame('Δραστηριότητες εστιατορίων', $entry->headquarters->activityDescr);
        $this->assertSame('5611', $entry->workplace->activityCode);
        $this->assertSame('ΠΛΑΤΕΙΑ ΕΛΕΥΘΕΡΩΤΩΝ', $entry->headquarters->address->street);
        $this->assertSame('11', $entry->headquarters->address->number);
        $this->assertSame('15232', $entry->workplace->address->zip);
        $this->assertSame('ΧΑΛΑΝΔΡΙΟΥ', $entry->workplace->address->area);
        $this->assertSame('9178', $entry->workplace->municipalityCode);
        $this->assertSame('ΙΔΙΩΤΙΚΗ ΚΕΦΑΛΑΙΟΥΧΙΚΗ ΕΤΑΙΡΕΙΑ (ΙΚΕ)', $entry->legalFormDescr);
        $this->assertSame('514002', $entry->occupationCode);
        $this->assertSame('ΜΑΓΕΙΡΟΙ, ΓΕΝΙΚΑ', $entry->occupationDescr);
        $this->assertSame(EmploymentType::FullTime, $entry->type);
        $this->assertSame(880.0, $entry->salary);
        $this->assertSame(40.0, $entry->weeklyHours);
        $this->assertSame('2025-11-24', $entry->from?->format('Y-m-d'));
        $this->assertSame('2025-11-27 17:12', $entry->to?->format('Y-m-d H:i'));
        $this->assertSame('880.00', $entry->raw['apodoxes']);
        $this->assertSame($history[0]->auditRecord, $history[1]->auditRecord);

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