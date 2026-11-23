<?php

namespace Sonole\GovGr\Tests;

use Illuminate\Support\Facades\Http;
use Sonole\GovGr\Amka\Data\AmkaStatus;
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
            <aaInput>15</aaInput><amkaInput>01019000015</amkaInput><amkaCurrent>01019000015</amkaCurrent>
            <lastDataLogDate>24/06/2025</lastDataLogDate><adtType>Τ</adtType><adt>Α0000000</adt><yearOfAdtPublished>2025</yearOfAdtPublished>
            <nationalityCountry>ΕΛΛΑΔΑ</nationalityCountry><nationalityCountryCode>GR</nationalityCountryCode><sex>ΑΡΡΕΝ</sex>
            <afm>00000000001</afm><birthSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</birthSurnameEl><idSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</idSurnameEl>
            <firstNameEl>ΓΙΑΝΝΗΣ</firstNameEl><fatherNameEl>ΠΕΤΡΟΣ</fatherNameEl><motherNameEl>ΜΑΡΙΑ ΤΕΡΕΖΑ</motherNameEl>
            <birthSurnameEn>PAPADOPOULOS</birthSurnameEn><idSurnameEn>PAPADOPOULOS</idSurnameEn><firstNameEn>GIANNIS</firstNameEn>
            <fatherNameEn>PETROS</fatherNameEn><motherNameEn>MARIA TEREZA</motherNameEn>
            <birthDate>02/01/1990</birthDate><fictitiousBirthDateIndication/><birthCountry>ΕΛΛΑΔΑ</birthCountry>
            <birthCountryCode>GR</birthCountryCode><birthMunicipality>ΑΘΗΝΑ</birthMunicipality><birthCountyCode>ΑΤΤΙ</birthCountyCode>
            <addressStreetNum>ΟΔΟΣ ΔΕΙΓΜΑ 1</addressStreetNum><addressCity>ΥΜΗΤΤΟΣ</addressCity><addressCountyCode>ΑΤΤΙ</addressCountyCode>
            <addressPostalCode>10000</addressPostalCode><addressCountry>ΕΛΛΑΔΑ</addressCountry><addressCountryCode>GR</addressCountryCode>
            <phone1>2100000000</phone1><phone2/><deathIndication/><deathDate/>',
            'amka2dataOutputRecord'));

        $person = Amka::amka2data('01019000015');

        $this->assertSame(Gender::Male, $person->gender);
        $this->assertSame(IdType::GreekPoliceIdCard, $person->idType);
        $this->assertTrue($person->hasPoliceId());
        $this->assertSame('000000001', $person->afm);
        $this->assertSame('1990-01-02', $person->birthDate?->format('Y-m-d'));
        $this->assertSame('ΑΘΗΝΑ', $person->birthPlace);
        $this->assertSame('ΟΔΟΣ ΔΕΙΓΜΑ 1', $person->address->street);
        $this->assertSame('ΥΜΗΤΤΟΣ', $person->address->area);
        $this->assertSame('ΕΛΛΑΔΑ', $person->address->country);
        $this->assertSame('ΑΤΤΙ', $person->addressCountyCode);
        $this->assertSame('GR', $person->addressCountryCode);
        $this->assertSame('ΜΑΡΙΑ ΤΕΡΕΖΑ', $person->motherName);
        $this->assertSame('ΠΑΠΑΔΟΠΟΥΛΟΣ', $person->birthSurname);
        $this->assertSame('PAPADOPOULOS', $person->surnameLatin);
        $this->assertSame('GIANNIS', $person->firstNameLatin);
        $this->assertSame('PETROS', $person->fatherNameLatin);
        $this->assertSame('MARIA TEREZA', $person->motherNameLatin);
        $this->assertSame('PAPADOPOULOS', $person->birthSurnameLatin);
        $this->assertFalse($person->isBirthDateFictitious);
        $this->assertSame('ΑΘΗΝΑ', $person->birthMunicipality);
        $this->assertSame('ΕΛΛΑΔΑ', $person->birthCountry);
        $this->assertSame('GR', $person->birthCountryCode);
        $this->assertSame('ΑΤΤΙ', $person->birthCountyCode);
        $this->assertSame(2025, $person->idIssuedYear);
        $this->assertSame('ΕΛΛΑΔΑ', $person->nationality);
        $this->assertSame('GR', $person->nationalityCode);
        $this->assertSame('2100000000', $person->phone);
        $this->assertNull($person->phone2);
        $this->assertFalse($person->isDeceased);
        $this->assertNull($person->deathDate);
        $this->assertSame('2025-06-24', $person->lastUpdatedAt?->format('Y-m-d'));
        $this->assertSame('Α0000000', $person->raw['adt']);
        $this->assertNotNull($person->auditRecord);
    }

    private const MINIMAL_PERSON = '<amkaCurrent>01019000015</amkaCurrent><idSurnameEl>Β</idSurnameEl><firstNameEl>Α</firstNameEl><sex>ΘΗΛΥ</sex>';

    public function test_amka2data_e_maps_the_status(): void
    {
        $this->fakeXml($this->soap(self::AMKA_NS, 'amka2dataE', self::MINIMAL_PERSON.'<status>ΠΚ</status>', 'amka2dataEOutputRecord'));

        $person = Amka::amka2dataE('01019000015');

        $this->assertSame(AmkaStatus::PaaypaRevoked, $person->status);
        $this->assertTrue($person->status->isCancelled());
        $this->assertSame(Gender::Female, $person->gender);
        $this->assertSame('ΠΚ', $person->raw['status']);
    }

    public function test_amka2data_does_not_report_a_status(): void
    {
        $this->fakeXml($this->soap(self::AMKA_NS, 'amka2data', self::MINIMAL_PERSON, 'amka2dataOutputRecord'));

        $this->assertNull(Amka::amka2data('01019000015')->status);
    }

    public function test_amka2data_le_treats_a_missing_status_as_an_active_amka(): void
    {
        $this->fakeXml($this->soap(self::AMKA_NS, 'amka2dataLE', '
            <amkaInput>01019000015</amkaInput><amkaCurrent>01019000015</amkaCurrent><nationalityCountry>ΕΛΛΑΔΑ</nationalityCountry>
            <sex>ΑΡΡΕΝ</sex><idSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</idSurnameEl><firstNameEl>ΓΙΑΝΝΗΣ</firstNameEl><birthDate>02/01/1990</birthDate>
            <addressStreetNum>ΟΔΟΣ 1</addressStreetNum><addressCity>ΑΘΗΝΑ</addressCity><addressPostalCode>10000</addressPostalCode>
            <phone1>2100000000</phone1><deathIndication/><deathDate/><status/>', 'amka2dataLEOutputRecord'));

        $person = Amka::amka2dataLE('01019000015');

        $this->assertSame(AmkaStatus::Active, $person->status);
        $this->assertTrue($person->status->isActive());
        $this->assertSame('ΓΙΑΝΝΗΣ', $person->firstName);
        $this->assertSame('ΟΔΟΣ 1', $person->address->street);
        $this->assertNull($person->idType);
        $this->assertNull($person->lastUpdatedAt);
    }

    public function test_amka2data_e_throws_not_found_for_a_business_rejection(): void
    {
        $this->fakeXml($this->error(self::AMKA_NS, 'amka2dataE', 'ΔΕΝ ΒΡΕΘΗΚΕ Ο ΑΜΚΑ'));

        $this->expectException(GovNotFoundException::class);

        Amka::amka2dataE('01019000015');
    }

    public function test_amka_person_flags_a_reported_death_and_a_fictitious_birth_date(): void
    {
        $this->fakeXml($this->soap(self::AMKA_NS, 'amka2data', '
            <amkaCurrent>01019000015</amkaCurrent><idSurnameEl>Β</idSurnameEl><firstNameEl>Α</firstNameEl>
            <birthDate>01/01/1990</birthDate><fictitiousBirthDateIndication>Π</fictitiousBirthDateIndication>
            <deathIndication>Λ</deathIndication><deathDate>05/06/2024</deathDate>', 'amka2dataOutputRecord'));

        $person = Amka::amka2data('01019000015');

        $this->assertTrue($person->isBirthDateFictitious);
        $this->assertTrue($person->isDeceased);
        $this->assertSame('2024-06-05', $person->deathDate?->format('Y-m-d'));
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
        $this->fakeXml($this->soap(self::AMKA_NS, 'data2amka', '
            <aaInput>17</aaInput><surnameInput>ΠΑΠΑΔΟΠΟΥΛΟΣ</surnameInput><firstNameInput>ΓΙΑΝΝΗΣ</firstNameInput>
            <motherNameInput>ΜΑΡΙΑ-ΤΕΡΕΖΑ</motherNameInput><birthDateInput>02/01/1990</birthDateInput><afmInput/><adtInput/>
            <amkaCurrent>01019000015</amkaCurrent><lastDataLogDate>24/06/2025</lastDataLogDate><adt>Α0000000</adt>
            <nationalityCountry>ΕΛΛΑΔΑ</nationalityCountry><nationalityCountryCode>GR</nationalityCountryCode><afm>00000000001</afm>
            <birthSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</birthSurnameEl><idSurnameEl>ΠΑΠΑΔΟΠΟΥΛΟΣ</idSurnameEl><firstNameEl>ΓΙΑΝΝΗΣ</firstNameEl>
            <motherNameEl>ΜΑΡΙΑ ΤΕΡΕ</motherNameEl><idSurnameEn>PAPADOPOULOS</idSurnameEn><firstNameEn>GIANNIS</firstNameEn>
            <birthDate>02/01/1990</birthDate><fictitiousBirthDateIndication/><deathIndication/><deathDate/>',
            'data2amkaOutputRecord'));

        $person = Amka::data2amka('ΠΑΠΑΔΟΠΟΥΛΟΣ', 'ΓΙΑΝΝΗΣ', birthDate: '02/01/1990');

        $this->assertSame('01019000015', $person->amka);
        $this->assertSame('000000001', $person->afm);
        $this->assertSame('Α0000000', $person->idNumber);
        $this->assertSame('ΜΑΡΙΑ ΤΕΡΕ', $person->motherName);
        $this->assertSame('PAPADOPOULOS', $person->surnameLatin);
        $this->assertSame('GR', $person->nationalityCode);
        $this->assertSame('2025-06-24', $person->lastUpdatedAt?->format('Y-m-d'));
        $this->assertFalse($person->isDeceased);
        // Not part of data2amka's answer:
        $this->assertNull($person->gender);
        $this->assertNull($person->idType);
        $this->assertNull($person->birthPlace);
        $this->assertNull($person->phone);
        $this->assertTrue($person->address->isEmpty());
        $this->assertSame('ΜΑΡΙΑ-ΤΕΡΕΖΑ', $person->raw['motherNameInput']);
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
