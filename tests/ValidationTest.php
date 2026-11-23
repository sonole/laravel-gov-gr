<?php

namespace Sonole\GovGr\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Sonole\GovGr\Amka\Facades\Amka;
use Sonole\GovGr\Core\Exceptions\GovApiException;
use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovNotFoundException;
use Sonole\GovGr\Core\Exceptions\GovUnavailableException;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Sonole\GovGr\Rules\ValidAfm;
use Sonole\GovGr\Rules\ValidAmka;
use Sonole\GovGr\TaxRegistry\Facades\TaxRegistry;

class ValidationTest extends TestCase
{
    private const VALID_AFM = '099360608';
    private const VALID_AMKA = '01019000015';

    protected function setUp(): void
    {
        parent::setUp();

        config(['govgr.validate_input' => true]);
    }

    public function test_the_afm_rule_checks_length_digits_and_the_check_digit(): void
    {
        $this->assertTrue(ValidAfm::passes(self::VALID_AFM));
        $this->assertFalse(ValidAfm::passes('099360609'));   // wrong check digit
        $this->assertFalse(ValidAfm::passes('12345678'));    // too short
        $this->assertFalse(ValidAfm::passes('12345678a'));
        $this->assertFalse(ValidAfm::passes('000000000'));
        $this->assertFalse(ValidAfm::passes(null));
    }

    public function test_the_amka_rule_checks_length_digits_and_the_check_digit(): void
    {
        $this->assertTrue(ValidAmka::passes(self::VALID_AMKA));
        $this->assertFalse(ValidAmka::passes('01019000016'));
        $this->assertFalse(ValidAmka::passes('0101900001'));
        $this->assertFalse(ValidAmka::passes('11111111111'));
        $this->assertFalse(ValidAmka::passes(null));
    }

    public function test_the_rules_work_in_the_laravel_validator_with_a_translated_message(): void
    {
        $validator = Validator::make(['afm' => '123', 'amka' => self::VALID_AMKA], ['afm' => [new ValidAfm], 'amka' => [new ValidAmka]]);

        $this->assertTrue($validator->fails());
        $this->assertSame(['afm'], array_keys($validator->errors()->toArray()));
        $this->assertSame('The afm is not a valid AFM.', $validator->errors()->first('afm'));
    }

    public function test_an_invalid_input_is_rejected_before_any_call_or_audit_row(): void
    {
        Http::fake();

        foreach ([
            fn () => TaxRegistry::retrieveInfoByAFM('099360609'),
            fn () => Amka::amka2data('01019000016'),
            fn () => Amka::data2amka('Α', 'Β', afm: '099360609'),
            fn () => Amka::confirmRelAmka('01019000016', parentAmka: self::VALID_AMKA),
            fn () => Amka::confirmRelAmka(self::VALID_AMKA, parentAmka: '01019000016'),
            fn () => Amka::confirmRelAmkaMultiple([self::VALID_AMKA, '123'], parentAmka: self::VALID_AMKA),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected an InvalidArgumentException.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        Http::assertNothingSent();
        $this->assertSame(0, AuditRecord::count());
    }

    public function test_the_check_can_be_turned_off(): void
    {
        config(['govgr.validate_input' => false]);
        Http::fake(['*' => Http::response('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><x:r xmlns:x="u"><errorRecord><errorCode>E</errorCode><errorDescr>Ανύπαρκτος Α.Φ.Μ.</errorDescr></errorRecord></x:r></soap:Body></soap:Envelope>', 200, ['Content-Type' => 'text/xml'])]);

        $this->expectException(GovNotFoundException::class);

        TaxRegistry::retrieveInfoByAFM('099360609');
    }

    public function test_a_server_error_is_unavailable_and_keeps_its_status_and_audit_record(): void
    {
        Http::fake(['*' => Http::response('<html>boom</html>', 503)]);

        try {
            TaxRegistry::retrieveInfoByAFM(self::VALID_AFM);
            $this->fail('Expected GovUnavailableException.');
        } catch (GovUnavailableException $e) {
            $this->assertSame(503, $e->httpStatusCode());
            $this->assertNotNull($e->auditRecord());
        }
    }

    public function test_a_forbidden_response_is_unavailable_not_not_found(): void
    {
        // What ΚΕΔ answers an IP that is not whitelisted.
        Http::fake(['*' => Http::response('403 Forbidden You are not authorized to access the requested resource.', 403)]);

        foreach ([
            fn () => Amka::amka2data(self::VALID_AMKA),
            fn () => Amka::data2amka('Α', 'Β', birthDate: '01/01/1990'),
            fn () => Amka::confirmRelAmka(self::VALID_AMKA, parentAmka: self::VALID_AMKA),
            fn () => TaxRegistry::retrieveInfoByAFM(self::VALID_AFM),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected GovUnavailableException.');
            } catch (GovUnavailableException $e) {
                $this->assertNotInstanceOf(GovNotFoundException::class, $e);
                $this->assertSame(403, $e->httpStatusCode());
            }
        }
    }

    public function test_a_connection_failure_is_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(GovUnavailableException::class);

        Amka::amka2data(self::VALID_AMKA);
    }

    public function test_bad_credentials_are_unavailable_too_and_still_authentication_exceptions(): void
    {
        Http::fake(['*' => Http::response('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><faultstring>A security error was encountered when verifying the message</faultstring></soap:Fault></soap:Body></soap:Envelope>', 500, ['Content-Type' => 'text/xml'])]);

        try {
            Amka::amka2data(self::VALID_AMKA);
            $this->fail('Expected an exception.');
        } catch (GovUnavailableException $e) {
            $this->assertInstanceOf(GovAuthenticationException::class, $e);
            $this->assertInstanceOf(GovApiException::class, $e);
        }
    }
}
