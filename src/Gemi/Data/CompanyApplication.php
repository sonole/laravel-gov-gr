<?php

namespace Sonole\GovGr\Gemi\Data;

use Carbon\Carbon;
use Sonole\GovGr\Core\Support\Normalize;

/** One registration (καταχώριση) on a company's GEMI record: an item of getBasicInfoV3's businessApplications. */
final class CompanyApplication
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        /** Κωδικός Αριθμός Καταχώρησης. */
        public readonly ?string $kak,
        public readonly ?string $subjectId,
        public readonly ?string $subjectDescr,
        public readonly ?Carbon $date,
        public readonly ?Carbon $decisionDate,
        public readonly ?string $decisionStatusId,
        public readonly ?string $decisionStatusDescr,
        public readonly ?Carbon $registrationDate,
        /** Where the registered document can be downloaded. */
        public readonly ?string $url,
        /** True for the registrations that delete the company from GEMI (subject types 5, 22, 36, 52, 55, 81). */
        public readonly bool $isDeletion,
        public readonly array $raw = [],
    ) {
    }

    /** @param array<string, mixed> $record */
    public static function fromRecord(array $record): self
    {
        $text = fn (string $key): ?string => Normalize::field($record, $key);

        return new self(
            kak: $text('kak'),
            subjectId: $text('appSubjectId'),
            subjectDescr: $text('appSubjectDescr'),
            date: Normalize::date($text('appDate')),
            decisionDate: Normalize::date($text('appDecisionDate')),
            decisionStatusId: $text('appDecisionStatusId'),
            decisionStatusDescr: $text('appDecisionStatusDescr'),
            registrationDate: Normalize::date($text('appRegistrationDate')),
            url: $text('url'),
            isDeletion: Normalize::bool($record['deletionSubject'] ?? null) === true,
            raw: $record,
        );
    }
}
