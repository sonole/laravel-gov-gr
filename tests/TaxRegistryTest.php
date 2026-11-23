<?php

namespace Sonole\GovGr\Tests;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovServiceException;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Sonole\GovGr\TaxRegistry\Facades\TaxRegistry;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

class TaxRegistryTest extends TestCase
{
    private const AFM_RECORD_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:retrieveInfoByAFMResponse xmlns:ns2="http://gsis.ggps.interoperability/RegistryInterface">
              <retrieveInfoByAFMRecord>
                <basicInfo>
                  <afm>123456789</afm>
                  <onomasia>ΤΕΣΤ ΑΕ</onomasia>
                  <doyDescr>Α ΑΘΗΝΩΝ</doyDescr>
                </basicInfo>
              </retrieveInfoByAFMRecord>
              <callSequenceId>1</callSequenceId>
              <errorRecord/>
            </ns2:retrieveInfoByAFMResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    // Fake/placeholder personal/business data below (not from a real call).
    // Field names match a real response; values do not.

    private const AFM_FULL_RECORD_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:retrieveInfoByAFMResponse xmlns:ns2="http://gsis.ggps.interoperability/RegistryInterface">
              <retrieveInfoByAFMRecord>
                <basicInfo>
                  <afm>000000000</afm>
                  <doy>0000</doy>
                  <doyDescr>ΔΕΙΓΜΑ ΔΟΥ</doyDescr>
                  <iNiFlag>1</iNiFlag>
                  <deactivationFlag>1</deactivationFlag>
                  <onomasia>ΠΑΠΑΔΟΠΟΥΛΟΣ,,ΓΙΑΝΝΗΣ,ΠΕΤΡΟΣ</onomasia>
                  <mothersFirstName>ΜΑΡΙΑ</mothersFirstName>
                  <birthDate>1990-01-01T00:00:00+02:00</birthDate>
                  <indPhone>2100000000</indPhone>
                  <cntResidenceDescr>ΕΛΛΑΔΑ</cntResidenceDescr>
                  <residenceAddress>ΟΔΟΣ ΔΕΙΓΜΑ</residenceAddress>
                  <residenceAddressNo>1</residenceAddressNo>
                  <residenceZipCode>10000</residenceZipCode>
                  <residenceParDescr>ΔΕΙΓΜΑ</residenceParDescr>
                  <firmDoy>0000</firmDoy>
                  <firmAddress>ΟΔΟΣ ΔΕΙΓΜΑ</firmAddress>
                  <firmAddressNo>1</firmAddressNo>
                  <firmZipCode>10000</firmZipCode>
                  <firmParDescr>ΔΕΙΓΜΑ</firmParDescr>
                  <firmPhone>2100000000</firmPhone>
                </basicInfo>
                <firmActivitiesArray/>
                <membershipsArray/>
                <relationsArray/>
                <symmetoxesArray/>
                <sysxetiseisArray/>
                <branchArray/>
                <branchActivitiesArray/>
                <branchAbroadArray/>
                <firmAbroad/>
              </retrieveInfoByAFMRecord>
              <callSequenceId>1</callSequenceId>
              <errorRecord/>
            </ns2:retrieveInfoByAFMResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const PUBLIC_LOOKUP_FULL_XML = <<<'XML'
        <env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">
          <env:Body>
            <ns2:rgWsPublic2AfmMethodResponse xmlns:ns2="http://rgwspublic2/RgWsPublic2Service">
              <ns2:rgWsPublic2AfmMethodResult>
                <OUTPUT_REC>
                  <call_seq_id>000000000</call_seq_id>
                  <afm_called_by_rec>
                    <token_username>USERNAME@USERNAME</token_username>
                    <token_afm>000000000</token_afm>
                    <token_afm_fullname>ΔΕΙΓΜΑ ΑΕ</token_afm_fullname>
                    <afm_called_by>000000000</afm_called_by>
                    <afm_called_by_fullname>ΔΕΙΓΜΑ ΑΕ</afm_called_by_fullname>
                    <as_on_date>2026-01-01</as_on_date>
                  </afm_called_by_rec>
                  <basic_rec>
                    <afm>000000000</afm>
                    <doy>0000</doy>
                    <doy_descr>ΔΕΙΓΜΑ ΔΟΥ</doy_descr>
                    <i_ni_flag_descr>ΜΗ ΦΠ</i_ni_flag_descr>
                    <deactivation_flag>1</deactivation_flag>
                    <deactivation_flag_descr>ΕΝΕΡΓΟΣ ΑΦΜ</deactivation_flag_descr>
                    <firm_flag_descr>ΕΠΙΤΗΔΕΥΜΑΤΙΑΣ</firm_flag_descr>
                    <onomasia>ΔΕΙΓΜΑ ΑΝΩΝΥΜΗ ΕΤΑΙΡΙΑ</onomasia>
                    <commer_title/>
                    <legal_status_descr>ΑΕ</legal_status_descr>
                    <postal_address>ΟΔΟΣ ΔΕΙΓΜΑ</postal_address>
                    <postal_address_no>1</postal_address_no>
                    <postal_zip_code>10000</postal_zip_code>
                    <postal_area_description>ΔΕΙΓΜΑ</postal_area_description>
                    <regist_date>2000-01-01</regist_date>
                    <stop_date/>
                    <normal_vat_system_flag>Y</normal_vat_system_flag>
                  </basic_rec>
                  <firm_act_tab>
                    <item>
                      <firm_act_code>00000000</firm_act_code>
                      <firm_act_descr>ΔΕΙΓΜΑ</firm_act_descr>
                      <firm_act_kind>1</firm_act_kind>
                      <firm_act_kind_descr>ΚΥΡΙΑ</firm_act_kind_descr>
                    </item>
                  </firm_act_tab>
                  <error_rec/>
                </OUTPUT_REC>
              </ns2:rgWsPublic2AfmMethodResult>
            </ns2:rgWsPublic2AfmMethodResponse>
          </env:Body>
        </env:Envelope>
        XML;

    private const REGISTRY_ERROR_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:retrieveInfoByAFMResponse xmlns:ns2="http://gsis.ggps.interoperability/RegistryInterface">
              <callSequenceId>1</callSequenceId>
              <errorRecord>
                <errorCode>WS_EXTERNAL_ERROR</errorCode>
                <errorDescr>Ο ΑΦΜ ΔΕΝ ΒΡΕΘΗΚΕ</errorDescr>
              </errorRecord>
            </ns2:retrieveInfoByAFMResponse>
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

    private const PUBLIC_LOOKUP_XML = <<<'XML'
        <env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">
          <env:Body>
            <ns2:rgWsPublic2AfmMethodResponse xmlns:ns2="http://rgwspublic2/RgWsPublic2Service">
              <ns2:rgWsPublic2AfmMethodResult>
                <OUTPUT_REC>
                  <basic_rec>
                    <onomasia>ΤΕΣΤ ΑΕ</onomasia>
                    <doy_descr>Α ΑΘΗΝΩΝ</doy_descr>
                  </basic_rec>
                  <error_rec/>
                </OUTPUT_REC>
              </ns2:rgWsPublic2AfmMethodResult>
            </ns2:rgWsPublic2AfmMethodResponse>
          </env:Body>
        </env:Envelope>
        XML;

    private const PUBLIC_ERROR_XML = <<<'XML'
        <env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">
          <env:Body>
            <ns2:rgWsPublic2AfmMethodResponse xmlns:ns2="http://rgwspublic2/RgWsPublic2Service">
              <ns2:rgWsPublic2AfmMethodResult>
                <OUTPUT_REC>
                  <basic_rec/>
                  <error_rec>
                    <error_code>RG_002</error_code>
                    <error_descr>ΔΕΝ ΒΡΕΘΗΚΕ Ο ΑΦΜ</error_descr>
                  </error_rec>
                </OUTPUT_REC>
              </ns2:rgWsPublic2AfmMethodResult>
            </ns2:rgWsPublic2AfmMethodResponse>
          </env:Body>
        </env:Envelope>
        XML;

    public function test_retrieve_info_by_afm_returns_the_output_record(): void
    {
        Http::fake(['*' => Http::response(self::AFM_RECORD_XML, 200, ['Content-Type' => 'text/xml'])]);

        $result = TaxRegistry::retrieveInfoByAFM('123456789');

        $this->assertSame('ΤΕΣΤ ΑΕ', $result->name);
        $this->assertSame('ΤΕΣΤ ΑΕ', $result->raw['basicInfo']['onomasia']);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<afm>123456789</afm>')
            && str_contains($r->body(), '<auditUserId>system@example.com</auditUserId>'));
    }

    public function test_retrieve_info_by_afm_returns_an_entity_with_the_full_record_on_raw(): void
    {
        Http::fake(['*' => Http::response(self::AFM_FULL_RECORD_XML, 200, ['Content-Type' => 'text/xml'])]);

        $entity = TaxRegistry::retrieveInfoByAFM('123456789');
        $raw = $entity->raw;

        $this->assertSame('ΠΑΠΑΔΟΠΟΥΛΟΣ,,ΓΙΑΝΝΗΣ,ΠΕΤΡΟΣ', $raw['basicInfo']['onomasia']);
        $this->assertSame('ΜΑΡΙΑ', $entity->mothersFirstName);
        $this->assertSame('ΟΔΟΣ ΔΕΙΓΜΑ', $entity->residence->street);
        $this->assertSame([], $raw['firmActivitiesArray']);
        $this->assertSame([], $raw['branchAbroadArray']);
        $this->assertNotNull($entity->auditRecord);
    }

    public function test_a_populated_registry_error_record_throws(): void
    {
        Http::fake(['*' => Http::response(self::REGISTRY_ERROR_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovServiceException::class);
        $this->expectExceptionMessage('Ο ΑΦΜ ΔΕΝ ΒΡΕΘΗΚΕ');

        TaxRegistry::retrieveInfoByAFM('123456789');
    }

    public function test_a_registry_soap_fault_throws_an_authentication_exception(): void
    {
        Http::fake(['*' => Http::response(self::FAULT_XML, 500, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovAuthenticationException::class);

        TaxRegistry::retrieveInfoByAFM('123456789');
    }

    public function test_retrieve_infos_by_adt_sends_adt_and_kind(): void
    {
        Http::fake(['*' => Http::response(self::AFM_RECORD_XML, 200, ['Content-Type' => 'text/xml'])]);

        TaxRegistry::retrieveInfosByADT('AB123456', 1);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<adt>AB123456</adt>')
            && str_contains($r->body(), '<kind>1</kind>'));
    }

    public function test_retrieve_info_by_name_omits_optional_fields_when_not_given(): void
    {
        Http::fake(['*' => Http::response(self::AFM_RECORD_XML, 200, ['Content-Type' => 'text/xml'])]);

        TaxRegistry::retrieveInfoByName(surnameA: 'ΠΑΠΑΔΟΠΟΥΛΟΣ', fathersFirstName: 'ΓΕΩΡΓΙΟΣ');

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<surnameA>ΠΑΠΑΔΟΠΟΥΛΟΣ</surnameA>')
            && ! str_contains($r->body(), '<surnameB>'));
    }

    public function test_empty_registry_credentials_are_rejected_before_any_http_call_or_audit_write(): void
    {
        Http::fake(['*' => Http::response(self::AFM_RECORD_XML, 200, ['Content-Type' => 'text/xml'])]);
        config(['govgr.tax-registry.registry.pilot.password' => '']);

        try {
            TaxRegistry::retrieveInfoByAFM('123456789');
            $this->fail('Expected a GovAuthenticationException.');
        } catch (GovAuthenticationException $e) {
            $this->assertStringContainsString('TAX_REGISTRY_REGISTRY_PILOT', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_public_lookup_sends_a_soap12_envelope_with_the_afm(): void
    {
        Http::fake(['*' => Http::response(self::PUBLIC_LOOKUP_XML, 200, ['Content-Type' => 'application/soap+xml'])]);

        $result = TaxRegistry::lookupByAfm('123456789');

        $this->assertSame('ΤΕΣΤ ΑΕ', $result['result']['basic_rec']['onomasia']);

        Http::assertSent(function (ClientRequest $r) {
            return str_contains($r->body(), 'http://www.w3.org/2003/05/soap-envelope')
                && str_contains($r->body(), '<ns3:afm_called_for>123456789</ns3:afm_called_for>')
                && str_contains($r->body(), '<ns3:afm_called_by/>')
                && $r->header('Content-Type')[0] === 'application/soap+xml; charset=utf-8';
        });
    }

    public function test_public_lookup_sends_afm_called_by_when_given(): void
    {
        Http::fake(['*' => Http::response(self::PUBLIC_LOOKUP_XML, 200, ['Content-Type' => 'application/soap+xml'])]);

        TaxRegistry::lookupByAfm('123456789', afmCalledBy: '987654321');

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<ns3:afm_called_by>987654321</ns3:afm_called_by>'));
    }

    public function test_public_lookup_returns_the_full_record_shape(): void
    {
        Http::fake(['*' => Http::response(self::PUBLIC_LOOKUP_FULL_XML, 200, ['Content-Type' => 'application/soap+xml'])]);

        $result = TaxRegistry::lookupByAfm('123456789')['result'];

        $this->assertSame('ΔΕΙΓΜΑ ΑΝΩΝΥΜΗ ΕΤΑΙΡΙΑ', $result['basic_rec']['onomasia']);
        $this->assertSame('ΔΕΙΓΜΑ ΑΕ', $result['afm_called_by_rec']['token_afm_fullname']);
        $this->assertSame('ΚΥΡΙΑ', $result['firm_act_tab']['item']['firm_act_kind_descr']);
    }

    public function test_public_lookup_does_not_write_an_audit_record_by_default(): void
    {
        Http::fake(['*' => Http::response(self::PUBLIC_LOOKUP_XML, 200, ['Content-Type' => 'application/soap+xml'])]);

        $result = TaxRegistry::lookupByAfm('123456789');

        $this->assertNull($result['audit_record']);
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_public_lookup_writes_an_audit_record_when_enabled(): void
    {
        config(['govgr.tax-registry.public.audit_enabled' => true]);
        Http::fake(['*' => Http::response(self::PUBLIC_LOOKUP_XML, 200, ['Content-Type' => 'application/soap+xml'])]);

        $result = TaxRegistry::lookupByAfm('123456789');

        $this->assertNotNull($result['audit_record']);
        $this->assertSame(1, AuditRecord::count());
        $this->assertSame(GovService::TaxRegistryPublic, AuditRecord::query()->first()->service);
    }

    public function test_a_populated_public_error_record_throws(): void
    {
        Http::fake(['*' => Http::response(self::PUBLIC_ERROR_XML, 200, ['Content-Type' => 'application/soap+xml'])]);

        $this->expectException(GovServiceException::class);
        $this->expectExceptionMessage('ΔΕΝ ΒΡΕΘΗΚΕ Ο ΑΦΜ');

        TaxRegistry::lookupByAfm('123456789');
    }

    public function test_empty_public_credentials_are_rejected_before_any_http_call_or_audit_write(): void
    {
        Http::fake(['*' => Http::response(self::PUBLIC_LOOKUP_XML, 200, ['Content-Type' => 'application/soap+xml'])]);
        config(['govgr.tax-registry.public.pilot.password' => '']);

        try {
            TaxRegistry::lookupByAfm('123456789');
            $this->fail('Expected a GovAuthenticationException.');
        } catch (GovAuthenticationException $e) {
            $this->assertStringContainsString('TAX_REGISTRY_PUBLIC_PILOT', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }
}
