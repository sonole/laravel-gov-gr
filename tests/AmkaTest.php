<?php

namespace Sonole\GovGr\Tests;

use Sonole\GovGr\Amka\Data\IdType;
use Sonole\GovGr\Amka\Facades\Amka;
use Sonole\GovGr\Core\Enums\Gender;
use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovServiceException;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AmkaTest extends TestCase
{
    private const SUCCESS_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:amka2dataResponse xmlns:ns2="http://gsis.ggps.interoperability/AmkaInfoInterface">
              <amka2dataOutputRecord>
                <amkaInput>12345678901</amkaInput>
                <idSurnameEl>ΔΕΙΓΜΑ</idSurnameEl>
                <firstNameEl>ΔΕΙΓΜΑ</firstNameEl>
              </amka2dataOutputRecord>
              <callSequenceId>1</callSequenceId>
              <callSequenceDate>2020-05-08T08:14:56.160+03:00</callSequenceDate>
              <errorRecord/>
            </ns2:amka2dataResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const ERROR_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:amka2dataResponse xmlns:ns2="http://gsis.ggps.interoperability/AmkaInfoInterface">
              <errorRecord>
                <errorCode>WS_EXTERNAL_ERROR</errorCode>
                <errorDescr>ΔΕΝ ΒΡΕΘΗΚΕ Ο ΑΜΚΑ</errorDescr>
              </errorRecord>
            </ns2:amka2dataResponse>
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

    private const MISSING_HEADER_FAULT_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <soap:Fault>
              <faultcode>soap:Server</faultcode>
              <faultstring>These policy alternatives can not be satisfied: {http://docs.oasis-open.org/ws-sx/ws-securitypolicy/200702}SupportingTokens {http://docs.oasis-open.org/ws-sx/ws-securitypolicy/200702}UsernameToken</faultstring>
            </soap:Fault>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const MULTIPLE_SINGLE_ITEM_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:confirmRelAmkaMultipleResponse xmlns:ns2="http://gsis.ggps.interoperability/confirmRelAmkaInterface">
              <confirmRelAmkaMultipleOutputRecord>
                <confirmList>
                  <item>
                    <relAmka>123</relAmka>
                    <confirmation>true</confirmation>
                    <info>TEKNO</info>
                  </item>
                </confirmList>
              </confirmRelAmkaMultipleOutputRecord>
              <callSequenceId>1</callSequenceId>
              <callSequenceDate>2020-05-22T10:54:50.841+03:00</callSequenceDate>
              <errorRecord/>
            </ns2:confirmRelAmkaMultipleResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    // Fake/placeholder personal data below (fictitious "ΠΑΠΑΔΟΠΟΥΛΟΣ" example person). Not from a real call.
    // Field names match a real response; values do not.

    private const AMKA2DATA_FULL_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:amka2dataResponse xmlns:ns2="http://gsis.ggps.interoperability/AmkaInfoInterface">
              <amka2dataOutputRecord>
                <aaInput>1</aaInput>
                <amkaInput>01234567890</amkaInput>
                <amkaCurrent>01234567890</amkaCurrent>
                <lastDataLogDate>01/01/2020</lastDataLogDate>
                <adtType>Τ</adtType>
                <adt>Α0000000</adt>
                <yearOfAdtPublished>2020</yearOfAdtPublished>
                <nationalityCountry>ΕΛΛΑΔΑ</nationalityCountry>
                <nationalityCountryCode>GR</nationalityCountryCode>
                <sex>ΑΡΡΕΝ</sex>
                <afm>000000001</afm>
                <birthSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</birthSurnameEl>
                <idSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</idSurnameEl>
                <firstNameEl>ΓΙΑΝΝΗΣ</firstNameEl>
                <fatherNameEl>ΠΕΤΡΟΣ</fatherNameEl>
                <motherNameEl>ΜΑΡΙΑ</motherNameEl>
                <birthSurnameEn>PAPADOPOULOS</birthSurnameEn>
                <idSurnameEn>PAPADOPOULOS</idSurnameEn>
                <firstNameEn>GIANNIS</firstNameEn>
                <fatherNameEn>PETROS</fatherNameEn>
                <motherNameEn>MARIA</motherNameEn>
                <birthDate>01/01/1990</birthDate>
                <fictitiousBirthDateIndication/>
                <birthCountry>ΕΛΛΑΔΑ</birthCountry>
                <birthCountryCode>GR</birthCountryCode>
                <birthMunicipality>ΑΘΗΝΑ</birthMunicipality>
                <birthCountyCode>ΑΤΤΙ</birthCountyCode>
                <addressStreetNum>ΟΔΟΣ ΔΕΙΓΜΑ 1</addressStreetNum>
                <addressCity>ΑΘΗΝΑ</addressCity>
                <addressCountyCode>ΑΤΤΙ</addressCountyCode>
                <addressPostalCode>10000</addressPostalCode>
                <addressCountry>ΕΛΛΑΔΑ</addressCountry>
                <addressCountryCode>GR</addressCountryCode>
                <phone1>2100000000</phone1>
                <phone2/>
                <deathIndication/>
                <deathDate/>
              </amka2dataOutputRecord>
              <callSequenceId>1</callSequenceId>
              <callSequenceDate>2026-01-01T00:00:00+02:00</callSequenceDate>
              <errorRecord/>
            </ns2:amka2dataResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const DATA2AMKA_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:data2amkaResponse xmlns:ns2="http://gsis.ggps.interoperability/AmkaInfoInterface">
              <data2amkaOutputRecord>
                <aaInput>1</aaInput>
                <surnameInput>ΠΑΠΑΔΟΠΟΥΛΟΣ</surnameInput>
                <firstNameInput>ΓΙΑΝΝΗΣ</firstNameInput>
                <fatherNameInput>ΠΕΤΡΟΣ</fatherNameInput>
                <motherNameInput/>
                <birthDateInput>01/01/1990</birthDateInput>
                <afmInput/>
                <adtInput/>
                <amkaCurrent>01234567890</amkaCurrent>
                <lastDataLogDate>01/01/2020</lastDataLogDate>
                <adt>Α0000000</adt>
                <nationalityCountry>ΕΛΛΑΔΑ</nationalityCountry>
                <nationalityCountryCode>GR</nationalityCountryCode>
                <afm>000000001</afm>
                <birthSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</birthSurnameEl>
                <idSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</idSurnameEl>
                <firstNameEl>ΓΙΑΝΝΗΣ</firstNameEl>
                <fatherNameEl>ΠΕΤΡΟΣ</fatherNameEl>
                <motherNameEl>ΜΑΡΙΑ</motherNameEl>
                <birthSurnameEn>PAPADOPOULOS</birthSurnameEn>
                <idSurnameEn>PAPADOPOULOS</idSurnameEn>
                <firstNameEn>GIANNIS</firstNameEn>
                <fatherNameEn>PETROS</fatherNameEn>
                <motherNameEn>MARIA</motherNameEn>
                <birthDate>01/01/1990</birthDate>
                <fictitiousBirthDateIndication/>
                <deathIndication/>
                <deathDate/>
              </data2amkaOutputRecord>
              <callSequenceId>1</callSequenceId>
              <callSequenceDate>2026-01-01T00:00:00+02:00</callSequenceDate>
              <errorRecord/>
            </ns2:data2amkaResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const CONFIRM_REL_AMKA_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:confirmRelAmkaResponse xmlns:ns2="http://gsis.ggps.interoperability/confirmRelAmkaInterface">
              <confirmRelAmkaOutputRecord>
                <confirmation>true</confirmation>
                <info>ΤΕΚΝΟ</info>
              </confirmRelAmkaOutputRecord>
              <callSequenceId>1</callSequenceId>
              <callSequenceDate>2026-01-01T00:00:00+02:00</callSequenceDate>
              <errorRecord/>
            </ns2:confirmRelAmkaResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    private const MULTIPLE_TWO_ITEMS_XML = <<<'XML'
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <ns2:confirmRelAmkaMultipleResponse xmlns:ns2="http://gsis.ggps.interoperability/confirmRelAmkaInterface">
              <confirmRelAmkaMultipleOutputRecord>
                <confirmList>
                  <item>
                    <relAmka>01234567890</relAmka>
                    <confirmation>true</confirmation>
                    <info>ΤΕΚΝΟ</info>
                  </item>
                  <item>
                    <relAmka>09876543210</relAmka>
                    <confirmation>true</confirmation>
                    <info>ΤΕΚΝΟ</info>
                  </item>
                </confirmList>
              </confirmRelAmkaMultipleOutputRecord>
              <callSequenceId>1</callSequenceId>
              <callSequenceDate>2026-01-01T00:00:00+02:00</callSequenceDate>
              <errorRecord/>
            </ns2:confirmRelAmkaMultipleResponse>
          </soap:Body>
        </soap:Envelope>
        XML;

    public function test_without_a_request_it_logs_the_system_identity(): void
    {
        Http::fake(['*' => Http::response(self::SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);

        Amka::amka2data('12345678901');

        Http::assertSent(fn (ClientRequest $request) => str_contains($request->body(), '<auditUserId>system@example.com</auditUserId>')
            && str_contains($request->body(), '<auditUserIp>198.51.100.1</auditUserIp>'));

        $record = AuditRecord::latest()->first();
        $this->assertSame('system@example.com', $record->user_email);
        $this->assertSame('198.51.100.1', $record->user_ip);
        $this->assertSame(self::SYSTEM_USER_ID, $record->user_id);
    }

    public function test_system_identity_without_configured_email_throws(): void
    {
        config(['govgr.audit.system_user_email' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('govgr.audit.system_user_email is not configured');

        Amka::amka2data('12345678901');
    }

    public function test_system_identity_without_configured_ip_throws(): void
    {
        config(['govgr.audit.system_ip' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('govgr.audit.system_ip is not configured');

        Amka::amka2data('12345678901');
    }

    public function test_system_identity_without_a_matching_user_throws(): void
    {
        config(['govgr.audit.system_user_email' => 'ghost@example.com']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No user found with email [ghost@example.com]');

        Amka::amka2data('12345678901');
    }

    public function test_system_identity_user_id_is_cached_across_calls(): void
    {
        Http::fake(['*' => Http::response(self::SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);

        Amka::amka2data('12345678901');

        DB::table('users')->where('id', self::SYSTEM_USER_ID)->delete();

        // Still resolves. The id was cached on the first call above, so this
        // doesn't re-query (and would throw if it did, since the row is gone).
        Amka::amka2data('12345678901');

        $this->assertSame(2, AuditRecord::where('user_id', self::SYSTEM_USER_ID)->count());
    }

    public function test_protocol_and_transaction_id_are_derived_from_the_audit_records_own_id(): void
    {
        Http::fake(['*' => Http::response(self::SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);

        Amka::amka2data('12345678901');

        $record = AuditRecord::latest()->first();
        $expectedProtocol = $record->id.'/'.$record->transaction_date->format('d-m-Y');

        $this->assertSame($expectedProtocol, $record->protocol);

        Http::assertSent(function (ClientRequest $request) use ($record, $expectedProtocol) {
            return str_contains($request->body(), "<auditProtocol>{$expectedProtocol}</auditProtocol>")
                && str_contains($request->body(), "<auditTransactionId>{$record->id}</auditTransactionId>");
        });
    }

    public function test_records_the_specific_service_and_environment(): void
    {
        Http::fake(['*' => Http::response(self::SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);

        Amka::amka2data('12345678901');

        $record = AuditRecord::query()->latest('id')->first();

        $this->assertSame(GovService::AmkaInfo, $record->service);
        $this->assertSame('pilot', $record->environment);

        Http::fake(['*' => Http::response(self::MULTIPLE_SINGLE_ITEM_XML, 200, ['Content-Type' => 'text/xml'])]);

        Amka::confirmRelAmkaMultiple(['123'], parentAmka: '456');

        $record = AuditRecord::query()->latest('id')->first();

        $this->assertSame(GovService::AmkaRelationship, $record->service);
        $this->assertSame('pilot', $record->environment);
    }

    public function test_with_an_authenticated_request_it_logs_the_real_identity(): void
    {
        Http::fake(['*' => Http::response(self::SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);

        $user = new FakeAuthenticatable(42, 'tester@example.com');
        $request = Request::create('/amka', 'POST', server: ['REMOTE_ADDR' => '203.0.113.5']);
        $request->setUserResolver(fn () => $user);

        Amka::amka2data('12345678901', $request);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<auditUserId>tester@example.com</auditUserId>')
            && str_contains($r->body(), '<auditUserIp>203.0.113.5</auditUserIp>'));

        $record = AuditRecord::latest()->first();
        $this->assertSame('tester@example.com', $record->user_email);
        $this->assertSame('203.0.113.5', $record->user_ip);
        $this->assertSame(42, $record->user_id);
    }

    public function test_an_identity_email_over_100_chars_is_rejected_before_any_http_call_or_audit_write(): void
    {
        Http::fake(['*' => Http::response(self::SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);

        $longEmail = str_repeat('a', 95).'@x.com';
        $this->assertGreaterThan(100, strlen($longEmail));

        $user = new FakeAuthenticatable(1, $longEmail);
        $request = Request::create('/amka', 'POST');
        $request->setUserResolver(fn () => $user);

        try {
            Amka::amka2data('12345678901', $request);
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('100 characters or fewer', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_a_populated_error_record_throws(): void
    {
        Http::fake(['*' => Http::response(self::ERROR_XML, 200, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovServiceException::class);
        $this->expectExceptionMessage('ΔΕΝ ΒΡΕΘΗΚΕ Ο ΑΜΚΑ');

        Amka::amka2data('12345678901');
    }

    public function test_a_thrown_exception_carries_the_audit_record_and_http_status(): void
    {
        Http::fake(['*' => Http::response(self::ERROR_XML, 200, ['Content-Type' => 'text/xml'])]);

        try {
            Amka::amka2data('12345678901');
            $this->fail('Expected an GovServiceException.');
        } catch (GovServiceException $e) {
            $record = AuditRecord::latest('id')->first();

            $this->assertSame($record->id, $e->auditRecord()->id);
            $this->assertSame($record->protocol, $e->auditRecord()->protocol);
            $this->assertSame(200, $e->httpStatusCode());
            $this->assertSame(200, $record->status_code);
        }
    }

    public function test_a_soap_fault_throws_an_authentication_exception_with_a_clearer_message(): void
    {
        Http::fake(['*' => Http::response(self::FAULT_XML, 500, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovAuthenticationException::class);
        $this->expectExceptionMessage('AMKA rejected the request: invalid username or password.');

        Amka::amka2data('12345678901');
    }

    public function test_a_soap_fault_records_the_actual_http_status(): void
    {
        Http::fake(['*' => Http::response(self::FAULT_XML, 500, ['Content-Type' => 'text/xml'])]);

        try {
            Amka::amka2data('12345678901');
            $this->fail('Expected an GovAuthenticationException.');
        } catch (GovAuthenticationException $e) {
            $this->assertSame(500, $e->httpStatusCode());
            $this->assertSame(500, $e->auditRecord()->status_code);
        }
    }

    public function test_a_missing_security_header_fault_throws_an_authentication_exception_with_a_clearer_message(): void
    {
        Http::fake(['*' => Http::response(self::MISSING_HEADER_FAULT_XML, 500, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovAuthenticationException::class);
        $this->expectExceptionMessage('AMKA rejected the request: no WS-Security credentials were sent.');

        Amka::amka2data('12345678901');
    }

    public function test_empty_credentials_are_rejected_before_any_http_call_or_audit_write(): void
    {
        Http::fake(['*' => Http::response(self::SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);
        config(['govgr.amka.info.pilot.password' => '']);

        try {
            Amka::amka2data('12345678901');
            $this->fail('Expected an GovAuthenticationException.');
        } catch (GovAuthenticationException $e) {
            $this->assertStringContainsString('AMKA_INFO_PILOT', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_empty_audit_unit_is_rejected_before_any_http_call_or_audit_write(): void
    {
        Http::fake(['*' => Http::response(self::SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);
        config(['govgr.audit.unit' => null]);

        try {
            Amka::amka2data('12345678901');
            $this->fail('Expected an GovServiceException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('GOVGR_AUDIT_UNIT', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_special_characters_in_credentials_and_fields_are_escaped_into_valid_xml(): void
    {
        config(['govgr.amka.info.pilot.password' => 'p&ss<word>"\'']);

        Http::fakeSequence()->push(self::DATA2AMKA_XML, 200, ['Content-Type' => 'text/xml']);

        Amka::data2amka(surname: 'O\'Brien & Sons', firstName: 'A<B>C', afm: '123456789');

        Http::assertSent(function (ClientRequest $r) {
            $this->assertStringContainsString('<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">p&amp;ss&lt;word&gt;&quot;&apos;</wsse:Password>', $r->body());
            $this->assertStringContainsString('<surname>O&apos;Brien &amp; Sons</surname>', $r->body());
            $this->assertStringContainsString('<firstName>A&lt;B&gt;C</firstName>', $r->body());

            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($r->body()), 'Generated envelope is not well-formed XML.');

            return true;
        });
    }

    public function test_confirm_rel_amka_rejects_partial_parent_details(): void
    {
        Http::fake();

        try {
            Amka::confirmRelAmka(relAmka: '123', parentFirstname: 'ΜΑΡΙΟΣ', parentSurname: 'ΓΕΩΡΓΙΟΥ');
            $this->fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString("parent's details are incomplete", $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_confirm_rel_amka_accepts_parent_amka_alone(): void
    {
        Http::fake(['*' => Http::response(self::MULTIPLE_SINGLE_ITEM_XML, 200, ['Content-Type' => 'text/xml'])]);

        Amka::confirmRelAmka(relAmka: '123', parentAmka: '456');

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<parentAmka>456</parentAmka>'));
    }

    public function test_confirm_rel_amka_accepts_all_parent_personal_details(): void
    {
        Http::fake(['*' => Http::response(self::MULTIPLE_SINGLE_ITEM_XML, 200, ['Content-Type' => 'text/xml'])]);

        Amka::confirmRelAmka(
            relAmka: '123',
            parentFirstname: 'ΜΑΡΙΟΣ',
            parentSurname: 'ΓΕΩΡΓΙΟΥ',
            parentFathername: 'ΠΑΝΑΓΙΩΤΗΣ',
            parentMothername: 'ΜΑΡΙΑ',
            parentBirthdate: '09/02/1998',
        );

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->body(), '<parentBirthdate>09/02/1998</parentBirthdate>'));
    }

    public function test_confirm_rel_amka_rejects_malformed_parent_birthdate(): void
    {
        Http::fake();

        try {
            Amka::confirmRelAmka(
                relAmka: '123',
                parentFirstname: 'ΜΑΡΙΟΣ',
                parentSurname: 'ΓΕΩΡΓΙΟΥ',
                parentFathername: 'ΠΑΝΑΓΙΩΤΗΣ',
                parentMothername: 'ΜΑΡΙΑ',
                parentBirthdate: '1998-02-09',
            );
            $this->fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('DD/MM/YYYY', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_amka2data_returns_a_person_with_the_full_record_on_raw(): void
    {
        Http::fake(['*' => Http::response(self::AMKA2DATA_FULL_XML, 200, ['Content-Type' => 'text/xml'])]);

        $person = Amka::amka2data('01234567890');

        $this->assertSame('01/01/1990', $person->raw['birthDate']);
        $this->assertSame('1990-01-01', $person->birthDate?->format('Y-m-d'));
        $this->assertSame('ΑΘΗΝΑ', $person->raw['addressCity']);
        $this->assertSame('ΑΘΗΝΑ', $person->address->area);
        $this->assertSame('2100000000', $person->phone);
        $this->assertSame([], $person->raw['deathIndication']);
        $this->assertSame(Gender::Male, $person->gender);
        $this->assertSame(IdType::GreekPoliceIdCard, $person->idType);
        $this->assertSame('amka_info', $person->auditRecord['service']->value ?? 'amka_info');
        $this->assertNotNull($person->auditRecord);
    }

    public function test_data2amka_returns_a_person_with_the_current_amka(): void
    {
        Http::fake(['*' => Http::response(self::DATA2AMKA_XML, 200, ['Content-Type' => 'text/xml'])]);

        $person = Amka::data2amka(surname: 'ΠΑΠΑΔΟΠΟΥΛΟΣ', firstName: 'ΓΙΑΝΝΗΣ', birthDate: '01/01/1990');

        $this->assertSame('01234567890', $person->amka);
        $this->assertSame('ΠΑΠΑΔΟΠΟΥΛΟΣ', $person->surname);
        $this->assertNull($person->gender);
        $this->assertSame('ΠΑΠΑΔΟΠΟΥΛΟΣ', $person->raw['surnameInput']);
        $this->assertSame([], $person->raw['motherNameInput']);
    }

    public function test_confirm_rel_amka_returns_a_confirmation_with_the_raw_record(): void
    {
        Http::fake(['*' => Http::response(self::CONFIRM_REL_AMKA_XML, 200, ['Content-Type' => 'text/xml'])]);

        $confirmation = Amka::confirmRelAmka(relAmka: '123', parentAmka: '456');

        $this->assertTrue($confirmation->confirmed);
        $this->assertSame('123', $confirmation->relAmka);
        $this->assertSame('ΤΕΚΝΟ', $confirmation->info);
        $this->assertSame('true', $confirmation->raw['confirmation']);
        $this->assertNotNull($confirmation->auditRecord);
    }

    public function test_confirm_rel_amka_multiple_returns_one_confirmation_per_item(): void
    {
        Http::fake(['*' => Http::response(self::MULTIPLE_TWO_ITEMS_XML, 200, ['Content-Type' => 'text/xml'])]);

        $items = Amka::confirmRelAmkaMultiple(['01234567890', '09876543210'], parentAmka: '456');

        $this->assertCount(2, $items);
        $this->assertSame('01234567890', $items[0]->relAmka);
        $this->assertSame('09876543210', $items[1]->relAmka);
        $this->assertTrue($items[1]->confirmed);
        $this->assertSame($items[0]->auditRecord, $items[1]->auditRecord);
    }

    public function test_confirm_rel_amka_multiple_with_a_single_item_is_still_a_list(): void
    {
        Http::fake(['*' => Http::response(self::MULTIPLE_SINGLE_ITEM_XML, 200, ['Content-Type' => 'text/xml'])]);

        $items = Amka::confirmRelAmkaMultiple(['123'], parentAmka: '456');

        $this->assertCount(1, $items);
        $this->assertSame('123', $items[0]->relAmka);
    }

    public function test_confirm_rel_amka_multiple_rejects_an_empty_rel_amkas_list(): void
    {
        Http::fake();

        try {
            Amka::confirmRelAmkaMultiple([], parentAmka: '456');
            $this->fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('requires at least one relAmka', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_confirm_rel_amka_multiple_rejects_a_blank_rel_amka(): void
    {
        Http::fake();

        try {
            Amka::confirmRelAmkaMultiple(['01234567890', '  '], parentAmka: '456');
            $this->fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('non-empty string', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_amka_env_switches_endpoint_and_credentials(): void
    {
        Http::fake(['*' => Http::response(self::SUCCESS_XML, 200, ['Content-Type' => 'text/xml'])]);

        config(['govgr.amka.env' => 'production']);
        Amka::amka2data('12345678901');

        Http::assertSent(fn (ClientRequest $r) => $r->url() === 'https://production.test/amkaInfoService'
            && str_contains($r->body(), '<wsse:Username>production-user</wsse:Username>'));

        config(['govgr.amka.env' => 'pilot']);
        Amka::amka2data('12345678901');

        Http::assertSent(fn (ClientRequest $r) => $r->url() === 'https://pilot.test/amkaInfoService'
            && str_contains($r->body(), '<wsse:Username>pilot-user</wsse:Username>'));
    }
}
