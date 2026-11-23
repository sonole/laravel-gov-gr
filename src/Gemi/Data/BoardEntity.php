<?php

namespace Sonole\GovGr\Gemi\Data;

use Carbon\Carbon;
use Sonole\GovGr\Core\Data\Address;
use Sonole\GovGr\Core\Support\Normalize;

/** A legal person on a company's board or among its representatives: an item of getCompanyBoardV3's np list. */
final class BoardEntity
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        /** codeNP, the registry's id of the entity. */
        public readonly ?string $code,
        public readonly ?string $afm,
        /** arGemi */
        public readonly ?string $gemiNumber,
        /** businessName */
        public readonly string $name,
        public readonly ?string $legalTypeId,
        public readonly ?string $legalTypeDescr,
        public readonly ?Carbon $electionDate,
        public readonly ?string $relationId,
        public readonly ?string $relationDescr,
        public readonly ?string $relationType,
        /** activeMember */
        public readonly bool $isActive,
        public readonly ?Carbon $startDate,
        public readonly ?Carbon $endDate,
        public readonly bool $isRepresentativeAlone,
        public readonly bool $isRepresentativeInCommon,
        public readonly ?float $percentage,
        /** street, streetNumber, zipCode and city. */
        public readonly Address $address,
        public readonly ?string $prefectureId,
        public readonly ?string $prefectureDescr,
        public readonly ?string $phone,
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
            code: $text('codeNP'),
            afm: $text('afm'),
            gemiNumber: $text('arGemi'),
            name: (string) $text('businessName'),
            legalTypeId: $text('legalTypeId'),
            legalTypeDescr: $text('legalTypeDescr'),
            electionDate: Normalize::date($text('electionDate')),
            relationId: $text('relationId'),
            relationDescr: $text('relationDescr'),
            relationType: $text('relationType'),
            isActive: $bool('activeMember'),
            startDate: Normalize::date($text('startDate')),
            endDate: Normalize::date($text('endDate')),
            isRepresentativeAlone: $bool('isRepresentativeAlone'),
            isRepresentativeInCommon: $bool('isRepresentativeInCommon'),
            percentage: Normalize::number($record['percentage'] ?? null),
            address: new Address($text('street'), $text('streetNumber'), $text('zipCode'), $text('city')),
            prefectureId: $text('prefectureId'),
            prefectureDescr: $text('prefectureDescr'),
            phone: $text('phone'),
            raw: $record,
        );
    }
}
