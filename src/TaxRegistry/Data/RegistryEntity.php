<?php

namespace Sonole\GovGr\TaxRegistry\Data;

use Carbon\Carbon;
use Sonole\GovGr\Core\Data\Address;
use Sonole\GovGr\Core\Support\Normalize;

/**
 * The tax registry's record for an AFM: the result of retrieveInfoByAFM(), a natural person or a legal entity (see `kind`).
 *
 * `name` is the registry's onomasia as returned. For a natural person it is also split into
 * surname / firstName / fatherName; those stay null for a legal entity. `raw` is the whole basicInfo
 * the data came from, for the fields not mapped here.
 */
final class RegistryEntity
{
    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed>|null $auditRecord
     */
    public function __construct(
        public readonly string $afm,
        public readonly EntityKind $kind,
        public readonly bool $isActive,
        public readonly string $name,
        public readonly ?string $surname,
        public readonly ?string $firstName,
        public readonly ?string $fatherName,
        public readonly ?string $mothersFirstName,
        public readonly ?Carbon $birthDate,
        public readonly ?string $birthPlace,
        public readonly ?Carbon $deathDate,
        public readonly ?string $doy,
        public readonly ?string $doyDescr,
        public readonly ?string $idCardNo,
        public readonly ?string $citizenship,
        public readonly ?string $phone,
        public readonly Address $residence,
        public readonly Address $business,
        public readonly ?string $businessTitle,
        public readonly ?string $businessPhone,
        public readonly array $raw = [],
        /** The audit_records row of the call, as an array. */
        public readonly ?array $auditRecord = null,
    ) {
    }

    public function isNaturalPerson(): bool
    {
        return $this->kind === EntityKind::NaturalPerson;
    }

    /**
     * @param array<string, mixed> $record the retrieveInfoByAFMRecord: basicInfo plus any optional groups asked for
     * @param array<string, mixed>|null $auditRecord
     */
    public static function fromRecord(array $record, ?array $auditRecord = null): self
    {
        $info = (array) ($record['basicInfo'] ?? []);

        $text = fn (string $key): ?string => Normalize::field($info, $key);
        $kind = EntityKind::tryFrom((string) $text('iNiFlag')) ?? EntityKind::LegalEntity;
        $natural = $kind === EntityKind::NaturalPerson;

        // onomasia packs a natural person as "SURNAME,SECOND SURNAME,NAME,FATHER NAME".
        $parts = array_map('trim', explode(',', (string) $text('onomasia')));

        return new self(
            afm: $text('afm') ?? '',
            kind: $kind,
            isActive: $text('deactivationFlag') !== '2',
            name: (string) $text('onomasia'),
            surname: $natural ? ($parts[0] ?: null) : null,
            firstName: $natural ? (($parts[2] ?? '') ?: null) : null,
            fatherName: $natural ? (($parts[3] ?? '') ?: null) : null,
            mothersFirstName: $text('mothersFirstName'),
            birthDate: Normalize::date($text('birthDate')),
            birthPlace: $text('birthPlace'),
            deathDate: Normalize::date($text('deathDate')),
            doy: $text('doy'),
            doyDescr: $text('doyDescr'),
            idCardNo: $text('cardNo'),
            citizenship: $text('cntCitizenshipDescr'),
            phone: $text('indPhone'),
            residence: new Address(
                $text('residenceAddress'), $text('residenceAddressNo'), $text('residenceZipCode'),
                $text('residenceParDescr'), $text('cntResidenceDescr'),
            ),
            business: new Address(
                $text('firmAddress'), $text('firmAddressNo'), $text('firmZipCode'), $text('firmParDescr'),
            ),
            businessTitle: $text('firmCommerTitle'),
            businessPhone: $text('firmPhone'),
            raw: $record,
            auditRecord: $auditRecord,
        );
    }
}
