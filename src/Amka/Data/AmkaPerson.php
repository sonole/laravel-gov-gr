<?php

namespace Sonole\GovGr\Amka\Data;

use Sonole\GovGr\Core\Data\Address;
use Sonole\GovGr\Core\Enums\Gender;
use Sonole\GovGr\Core\Support\Normalize;
use Carbon\Carbon;

/**
 * What the AMKA service holds for a person: the result of amka2data(), data2amka(), amka2dataE() and
 * amka2dataLE(). Every documented field of amka2data is mapped. data2amka's answer is a subset: it carries no
 * gender, ID type, birth place, address or phones, so those stay null / empty there. amka2dataE adds the
 * `status` indication, and amka2dataLE is a lighter field set with that status (no ID type, birth place,
 * Latin birth surname, country codes, second phone or last-update date). `surname`, `firstName`, `fatherName` and `motherName` are the
 * Greek names, with the Latin ones as fallback when the Greek are blank; the Latin spellings are also on their
 * own `*Latin` properties. `raw` is the whole output record (for data2amka that includes the echoed *Input
 * fields of what was asked).
 */
final class AmkaPerson
{
    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed>|null $auditRecord
     */
    public function __construct(
        public readonly string $amka,
        public readonly ?string $afm,
        public readonly string $surname,
        public readonly string $firstName,
        public readonly ?string $fatherName,
        public readonly ?string $motherName,
        /** Επώνυμο κατά τη γέννηση (birthSurnameEl), when it differs from the current one. */
        public readonly ?string $birthSurname,
        public readonly ?string $surnameLatin,
        public readonly ?string $firstNameLatin,
        public readonly ?string $fatherNameLatin,
        public readonly ?string $motherNameLatin,
        public readonly ?string $birthSurnameLatin,
        public readonly ?Carbon $birthDate,
        /** True when the registry marks the birth date as fictitious (fictitiousBirthDateIndication = Π). */
        public readonly bool $isBirthDateFictitious,
        public readonly ?Gender $gender,
        /** The municipality, or the country for someone born abroad. */
        public readonly ?string $birthPlace,
        public readonly ?string $birthMunicipality,
        public readonly ?string $birthCountry,
        /** ISO country code (birthCountryCode), "GR" for Greece. */
        public readonly ?string $birthCountryCode,
        /** The first four letters of the Greek prefecture of birth (birthCountyCode), e.g. "ΑΤΤΙ". */
        public readonly ?string $birthCountyCode,
        /** adtType; null when the service sent a code outside the documented list (see ->raw). */
        public readonly ?IdType $idType,
        public readonly ?string $idNumber,
        /** yearOfAdtPublished */
        public readonly ?int $idIssuedYear,
        public readonly ?string $nationality,
        /** ISO country code (nationalityCountryCode). */
        public readonly ?string $nationalityCode,
        /** addressStreetNum (street and number together), postal code, city and country. */
        public readonly Address $address,
        /** The first four letters of the prefecture of the address (addressCountyCode). */
        public readonly ?string $addressCountyCode,
        /** ISO country code of the address (addressCountryCode). */
        public readonly ?string $addressCountryCode,
        /** phone1 */
        public readonly ?string $phone,
        public readonly ?string $phone2,
        /** True when a death has been reported (deathIndication Θ, or Λ with a death certificate). */
        public readonly bool $isDeceased,
        public readonly ?Carbon $deathDate,
        /** When the registry last recorded a change to these details (lastDataLogDate). */
        public readonly ?Carbon $lastUpdatedAt,
        /**
         * The ΠΑΑΥΠΑ indication of amka2dataE() / amka2dataLE(). Null from amka2data() and data2amka(), which do
         * not report it, and for a code outside the documented list (see ->raw['status']).
         */
        public readonly ?AmkaStatus $status,
        public readonly array $raw = [],
        /** The audit_records row of the call, as an array. */
        public readonly ?array $auditRecord = null,
    ) {
    }

    public function hasPoliceId(): bool
    {
        return $this->idType?->isPoliceIdCard() === true && $this->idNumber !== null;
    }

    /**
     * @param array<string, mixed> $record the amka2dataOutputRecord / data2amkaOutputRecord / amka2dataEOutputRecord / amka2dataLEOutputRecord
     * @param array<string, mixed>|null $auditRecord
     * @param bool $withStatus true for amka2dataE / amka2dataLE records, whose missing status means an active AMKA
     */
    public static function fromRecord(array $record, ?array $auditRecord = null, bool $withStatus = false): ?self
    {
        $text = fn (string $key): ?string => Normalize::field($record, $key);
        $greekOrLatin = fn (string $greek, string $latin): ?string => $text($greek) ?? $text($latin);

        $first = $greekOrLatin('firstNameEl', 'firstNameEn');
        $surname = $greekOrLatin('idSurnameEl', 'idSurnameEn');

        if ($first === null || $surname === null) {
            return null;
        }

        // Some services zero-pad the AFM; a real one is the rightmost 9 digits.
        $afm = $text('afm');
        $idYear = $text('yearOfAdtPublished');
        $dates = ['d/m/Y', 'Y-m-d'];

        return new self(
            amka: $text('amkaCurrent') ?? $text('amkaInput') ?? '',
            afm: $afm !== null && strlen($afm) >= 9 ? substr($afm, -9) : null,
            surname: $surname,
            firstName: $first,
            fatherName: $greekOrLatin('fatherNameEl', 'fatherNameEn'),
            motherName: $greekOrLatin('motherNameEl', 'motherNameEn'),
            birthSurname: $greekOrLatin('birthSurnameEl', 'birthSurnameEn'),
            surnameLatin: $text('idSurnameEn'),
            firstNameLatin: $text('firstNameEn'),
            fatherNameLatin: $text('fatherNameEn'),
            motherNameLatin: $text('motherNameEn'),
            birthSurnameLatin: $text('birthSurnameEn'),
            birthDate: Normalize::date($text('birthDate'), $dates),
            isBirthDateFictitious: $text('fictitiousBirthDateIndication') === 'Π',
            gender: match ($text('sex')) {
                'ΑΡΡΕΝ' => Gender::Male,
                'ΘΗΛΥ' => Gender::Female,
                default => null,
            },
            birthPlace: $text('birthMunicipality') ?? $text('birthCountry'),
            birthMunicipality: $text('birthMunicipality'),
            birthCountry: $text('birthCountry'),
            birthCountryCode: $text('birthCountryCode'),
            birthCountyCode: $text('birthCountyCode'),
            idType: IdType::tryFrom((string) $text('adtType')),
            idNumber: $text('adt'),
            idIssuedYear: $idYear !== null && ctype_digit($idYear) ? (int) $idYear : null,
            nationality: $text('nationalityCountry'),
            nationalityCode: $text('nationalityCountryCode'),
            address: new Address(
                $text('addressStreetNum'), null, $text('addressPostalCode'), $text('addressCity'), $text('addressCountry'),
            ),
            addressCountyCode: $text('addressCountyCode'),
            addressCountryCode: $text('addressCountryCode'),
            phone: $text('phone1'),
            phone2: $text('phone2'),
            isDeceased: in_array($text('deathIndication'), ['Θ', 'Λ'], true),
            deathDate: Normalize::date($text('deathDate'), $dates),
            lastUpdatedAt: Normalize::date($text('lastDataLogDate'), $dates),
            status: $withStatus ? AmkaStatus::tryFrom((string) $text('status')) : null,
            raw: $record,
            auditRecord: $auditRecord,
        );
    }
}
