<?php

namespace Sonole\GovGr\Gemi\Data;

use Carbon\Carbon;
use Sonole\GovGr\Core\Data\Address;
use Sonole\GovGr\Core\Support\Normalize;

/**
 * A natural person on a company's board or among its representatives: an item of getCompanyBoardV3's fp list.
 * Home address, birth date and identity document are real personal data; handle, log and display with care.
 */
final class BoardMember
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly ?string $afm,
        /** lastname */
        public readonly ?string $surname,
        public readonly ?string $firstName,
        public readonly ?string $fatherName,
        public readonly ?string $motherName,
        public readonly ?Carbon $birthDate,
        public readonly ?string $citizenship,
        /** Ownership percentage, where the relation carries one. */
        public readonly ?float $percentage,
        public readonly ?string $homePrefectureId,
        public readonly ?string $homePrefecture,
        public readonly ?string $homeMunicipalityId,
        public readonly ?string $homeMunicipality,
        /** homeStreet, homeNumber, zipCode and homeCity. */
        public readonly Address $address,
        public readonly ?string $phone,
        public readonly bool $isRepresentativeAlone,
        public readonly bool $isRepresentativeInCommon,
        public readonly ?string $relationId,
        /** "ΠΡΟΕΔΡΟΣ", "Διαχειριστής", "Ατομικό Μέλος", ... */
        public readonly ?string $relationDescr,
        /** "BOARD", "OTHER", ... */
        public readonly ?string $relationType,
        /** codeFP, the registry's id of the person. */
        public readonly ?string $code,
        /** activeMember */
        public readonly bool $isActive,
        public readonly ?Carbon $electionDate,
        public readonly ?Carbon $startDate,
        public readonly ?Carbon $endDate,
        public readonly ?string $idNumber,
        /** identityType: "ΑΣΤΥΝΟΜΙΚΗ ΤΑΥΤΟΤΗΤΑ", ... */
        public readonly ?string $idType,
        /** identityDate */
        public readonly ?Carbon $idIssuedAt,
        /** identityOffice */
        public readonly ?string $idIssuer,
        public readonly array $raw = [],
    ) {
    }

    public function isRepresentative(): bool
    {
        return $this->isRepresentativeAlone || $this->isRepresentativeInCommon;
    }

    /** @param array<string, mixed> $record */
    public static function fromRecord(array $record): self
    {
        $text = fn (string $key): ?string => Normalize::field($record, $key);
        $bool = fn (string $key): bool => Normalize::bool($record[$key] ?? null) === true;

        return new self(
            afm: $text('afm'),
            surname: $text('lastname'),
            firstName: $text('firstname'),
            fatherName: $text('fathersName'),
            motherName: $text('mothersName'),
            birthDate: Normalize::date($text('birthDate')),
            citizenship: $text('citizenship'),
            percentage: Normalize::number($record['percentage'] ?? null),
            homePrefectureId: $text('homePrefectureId'),
            homePrefecture: $text('homePrefecture'),
            homeMunicipalityId: $text('homeMunicipalityId'),
            homeMunicipality: $text('homeMunicipality'),
            address: new Address($text('homeStreet'), $text('homeNumber'), $text('zipCode'), $text('homeCity')),
            phone: $text('phone'),
            isRepresentativeAlone: $bool('isRepresentativeAlone'),
            isRepresentativeInCommon: $bool('isRepresentativeInCommon'),
            relationId: $text('relationId'),
            relationDescr: $text('relationDescr'),
            relationType: $text('relationType'),
            code: $text('codeFP'),
            isActive: $bool('activeMember'),
            electionDate: Normalize::date($text('electionDate')),
            startDate: Normalize::date($text('startDate')),
            endDate: Normalize::date($text('endDate')),
            idNumber: $text('identityNumber'),
            idType: $text('identityType'),
            idIssuedAt: Normalize::date($text('identityDate')),
            idIssuer: $text('identityOffice'),
            raw: $record,
        );
    }
}
