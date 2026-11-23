<?php

namespace Sonole\GovGr\Tests;

use Illuminate\Support\Facades\Http;
use Sonole\GovGr\Amka\Data\IdType;
use Sonole\GovGr\Amka\Facades\Amka;
use Sonole\GovGr\Core\Enums\Gender;
use Sonole\GovGr\Core\Exceptions\GovAuthenticationException;
use Sonole\GovGr\Core\Exceptions\GovDeactivatedException;
use Sonole\GovGr\Core\Exceptions\GovNotFoundException;
use Sonole\GovGr\TaxRegistry\Data\EntityKind;
use Sonole\GovGr\TaxRegistry\Facades\TaxRegistry;

class TypedResultsTest extends TestCase
{
    private function soap(string $ns, string $operation, string $record, string $recordTag): string
    {
        return <<<XML
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>
              <ns2:{$operation}Response xmlns:ns2="{$ns}">
                <{$recordTag}>{$record}</{$recordTag}>
                <callSequenceId>1</callSequenceId><errorRecord/>
              </ns2:{$operation}Response>
            </soap:Body></soap:Envelope>
            XML;
    }

    private function error(string $ns, string $operation, string $description): string
    {
        return <<<XML
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>
              <ns2:{$operation}Response xmlns:ns2="{$ns}">
                <errorRecord><errorCode>WS_EXTERNAL_ERROR</errorCode><errorDescr>{$description}</errorDescr></errorRecord>
              </ns2:{$operation}Response>
            </soap:Body></soap:Envelope>
            XML;
    }

    private function fakeXml(string $xml, int $status = 200): void
    {
        Http::fake(['*' => Http::response($xml, $status, ['Content-Type' => 'text/xml'])]);
    }

    private const AMKA_NS = 'http://gsis.ggps.interoperability/AmkaInfoInterface';
    private const REGISTRY_NS = 'http://gsis.ggps.interoperability/RegistryInterface';

    public function test_amka_person_maps_the_record_and_keeps_the_raw_one(): void
    {
        $this->fakeXml($this->soap(self::AMKA_NS, 'amka2data', '
            <amkaCurrent>01019000015</amkaCurrent><adtType>Τ</adtType><adt>Α0000000</adt><sex>ΑΡΡΕΝ</sex>
            <afm>00000000001</afm><idSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</idSurnameEl><firstNameEl>ΓΙΑΝΝΗΣ</firstNameEl>
            <fatherNameEl>ΠΕΤΡΟΣ</fatherNameEl><birthDate>02/01/1990</birthDate><birthMunicipality>ΑΘΗΝΑ</birthMunicipality>
            <addressStreetNum>ΟΔΟΣ ΔΕΙΓΜΑ 1</addressStreetNum><addressPostalCode>10000</addressPostalCode><phone2/><deathDate/>',
            'amka2dataOutputRecord'));

        $person = Amka::amka2data('01019000015');

        $this->assertSame(Gender::Male, $person->gender);
        $this->assertSame(IdType::GreekPoliceIdCard, $person->idType);
        $this->assertTrue($person->hasPoliceId());
        $this->assertSame('000000001', $person->afm);
        $this->assertSame('1990-01-02', $person->birthDate?->format('Y-m-d'));
        $this->assertSame('ΑΘΗΝΑ', $person->birthPlace);
        $this->assertSame('ΟΔΟΣ ΔΕΙΓΜΑ 1', $person->address->street);
        $this->assertNull($person->deathDate);
        $this->assertSame('Α0000000', $person->raw['adt']);
        $this->assertNotNull($person->auditRecord);
    }

    public function test_an_undocumented_id_type_is_null_but_stays_in_raw(): void
    {
        $this->fakeXml($this->soap(self::AMKA_NS, 'amka2data', '<adtType>?</adtType><adt>1</adt><firstNameEl>Α</firstNameEl><idSurnameEl>Β</idSurnameEl>', 'amka2dataOutputRecord'));

        $person = Amka::amka2data('01019000015');

        $this->assertNull($person->idType);
        $this->assertFalse($person->hasPoliceId());
        $this->assertSame('?', $person->raw['adtType']);
    }

    public function test_id_type_codes_are_unique_and_distinguish_lookalike_letters(): void
    {
        $values = array_map(fn (IdType $t) => $t->value, IdType::cases());

        $this->assertCount(31, $values);
        $this->assertCount(31, array_unique($values));
        $this->assertNotSame(IdType::from("\u{039D}"), IdType::from('N')); // Greek Ν vs Latin N
    }

    public function test_amka_person_throws_not_found_for_a_business_rejection(): void
    {
        $this->fakeXml($this->error(self::AMKA_NS, 'amka2data', 'ΔΕΝ ΒΡΕΘΗΚΕ Ο ΑΜΚΑ'));

        $this->expectException(GovNotFoundException::class);

        Amka::amka2data('01019000015');
    }

    public function test_amka_person_lets_a_server_error_through(): void
    {
        $this->fakeXml('<html>boom</html>', 500);

        try {
            Amka::amka2data('01019000015');
            $this->fail('Expected an exception.');
        } catch (GovNotFoundException) {
            $this->fail('A server error is not "not found".');
        } catch (\Sonole\GovGr\Core\Exceptions\GovApiException $e) {
            $this->assertSame(500, $e->httpStatusCode());
        }
    }

    public function test_data2amka_returns_the_person_with_the_current_amka(): void
    {
        $this->fakeXml($this->soap(self::AMKA_NS, 'data2amka', '<amkaCurrent>01019000015</amkaCurrent><idSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</idSurnameEl><firstNameEl>ΓΙΑΝΝΗΣ</firstNameEl>', 'data2amkaOutputRecord'));
        $this->assertSame('01019000015', Amka::data2amka('ΠΑΠΑΔΟΠΟΥΛΟΣ', 'ΓΙΑΝΝΗΣ', birthDate: '02/01/1990')->amka);
    }

    public function test_data2amka_throws_not_found_when_nothing_matches(): void
    {
        $this->fakeXml($this->error(self::AMKA_NS, 'data2amka', 'ΔΕΝ ΒΡΕΘΗΚΕ'));

        $this->expectException(GovNotFoundException::class);

        Amka::data2amka('ΠΑΠΑΔΟΠΟΥΛΟΣ', 'ΓΙΑΝΝΗΣ', birthDate: '02/01/1990');
    }

    public function test_retrieve_info_by_afm_maps_a_natural_person(): void
    {
        $this->fakeXml($this->soap(self::REGISTRY_NS, 'retrieveInfoByAFM', '<basicInfo>
            <afm>000000000</afm><iNiFlag>1</iNiFlag><deactivationFlag>1</deactivationFlag>
            <onomasia>ΠΑΠΑΔΟΠΟΥΛΟΣ,,ΓΙΑΝΝΗΣ,ΠΕΤΡΟΣ</onomasia><birthDate>1990-01-02T00:00:00+02:00</birthDate>
            <firmPhone/><residenceAddress>ΟΔΟΣ</residenceAddress></basicInfo>', 'retrieveInfoByAFMRecord'));

        $entity = TaxRegistry::retrieveInfoByAFM('000000000');

        $this->assertSame(EntityKind::NaturalPerson, $entity->kind);
        $this->assertTrue($entity->isNaturalPerson());
        $this->assertSame('ΠΑΠΑΔΟΠΟΥΛΟΣ', $entity->surname);
        $this->assertSame('ΓΙΑΝΝΗΣ', $entity->firstName);
        $this->assertSame('ΠΕΤΡΟΣ', $entity->fatherName);
        $this->assertSame('1990-01-02', $entity->birthDate?->format('Y-m-d'));
        $this->assertNull($entity->businessPhone);
        $this->assertSame('ΟΔΟΣ', $entity->residence->street);
        $this->assertSame('000000000', $entity->raw['basicInfo']['afm']);
        $this->assertNotNull($entity->auditRecord);
    }

    public function test_retrieve_info_by_afm_maps_a_legal_entity_without_person_parts(): void
    {
        $this->fakeXml($this->soap(self::REGISTRY_NS, 'retrieveInfoByAFM', '<basicInfo>
            <afm>099360608</afm><iNiFlag>2</iNiFlag><onomasia>ΔΕΙΓΜΑ ΑΕ</onomasia></basicInfo>', 'retrieveInfoByAFMRecord'));

        $entity = TaxRegistry::retrieveInfoByAFM('099360608');

        $this->assertSame(EntityKind::LegalEntity, $entity->kind);
        $this->assertSame('ΔΕΙΓΜΑ ΑΕ', $entity->name);
        $this->assertNull($entity->surname);
    }

    public function test_retrieve_info_by_afm_throws_not_found_for_an_unknown_afm(): void
    {
        $this->fakeXml($this->error(self::REGISTRY_NS, 'retrieveInfoByAFM', 'Ανύπαρκτος Α.Φ.Μ.'));

        $this->expectException(GovNotFoundException::class);

        TaxRegistry::retrieveInfoByAFM('123456789');
    }

    public function test_retrieve_info_by_afm_throws_deactivated_for_a_deactivated_afm(): void
    {
        $this->fakeXml($this->error(self::REGISTRY_NS, 'retrieveInfoByAFM', 'Ο Α.Φ.Μ. είναι απενεργοποιημένος'));

        $this->expectException(GovDeactivatedException::class);

        TaxRegistry::retrieveInfoByAFM('123456789');
    }

    public function test_retrieve_info_by_afm_lets_credential_errors_through(): void
    {
        $this->fakeXml('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><faultstring>A security error was encountered when verifying the message</faultstring></soap:Fault></soap:Body></soap:Envelope>', 500);

        $this->expectException(GovAuthenticationException::class);

        TaxRegistry::retrieveInfoByAFM('123456789');
    }
}
