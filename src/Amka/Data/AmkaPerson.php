<?php

namespace Sonole\GovGr\Amka\Data;

use Sonole\GovGr\Core\Data\Address;
use Sonole\GovGr\Core\Enums\Gender;
use Sonole\GovGr\Core\Support\Normalize;
use Carbon\Carbon;

/**
 * What the AMKA service holds for a person: the result of amka2data() and data2amka() (which has no gender
 * or ID type). Names are Greek, with the Latin ones as fallback.
 * `raw` is the whole amka2dataOutputRecord, for the fields not mapped here.
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
        public readonly ?Carbon $birthDate,
        public readonly ?Gender $gender,
        /** The municipality, or the country for someone born abroad. */
        public readonly ?string $birthPlace,
        /** adtType; null when the service sent a code outside the documented list (see ->raw). */
        public readonly ?IdType $idType,
        public readonly ?string $idNumber,
        public readonly ?string $nationality,
        public readonly Address $address,
        public readonly ?string $phone,
        public readonly ?Carbon $deathDate,
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
     * @param array<string, mixed> $record the amka2dataOutputRecord / data2amkaOutputRecord
     * @param array<string, mixed>|null $auditRecord
     */
    public static function fromRecord(array $record, ?array $auditRecord = null): ?self
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

        return new self(
            amka: $text('amkaCurrent') ?? $text('amkaInput') ?? '',
            afm: $afm !== null && strlen($afm) >= 9 ? substr($afm, -9) : null,
            surname: $surname,
            firstName: $first,
            fatherName: $greekOrLatin('fatherNameEl', 'fatherNameEn'),
            motherName: $greekOrLatin('motherNameEl', 'motherNameEn'),
            birthDate: Normalize::date($text('birthDate'), ['d/m/Y', 'Y-m-d']),
            gender: match ($text('sex')) {
                'ΑΡΡΕΝ' => Gender::Male,
                'ΘΗΛΥ' => Gender::Female,
                default => null,
            },
            birthPlace: $text('birthMunicipality') ?? $text('birthCountry'),
            idType: IdType::tryFrom((string) $text('adtType')),
            idNumber: $text('adt'),
            nationality: $text('nationalityCountry'),
            address: new Address(
                $text('addressStreetNum'), null, $text('addressPostalCode'), $text('addressCity'), $text('addressCountry'),
            ),
            phone: $text('phone1'),
            deathDate: Normalize::date($text('deathDate'), ['d/m/Y', 'Y-m-d']),
            raw: $record,
            auditRecord: $auditRecord,
        );
    }
}
