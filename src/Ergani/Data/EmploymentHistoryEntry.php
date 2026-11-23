<?php

namespace Sonole\GovGr\Ergani\Data;

use Carbon\Carbon;
use Sonole\GovGr\Core\Data\Address;
use Sonole\GovGr\Core\Support\Normalize;

/**
 * One period of employment: an item of Ergani::employmentHistoryByPeriod() (a getEmploymentHistoryByPeriod hist
 * record). The occupation is the most specific ΣΤΕΠ-92 level the service filled in (6, 4, 3 or 2 digits); every
 * level is on ->raw (step2Code ... step6Descr).
 */
final class EmploymentHistoryEntry
{
    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed>|null $auditRecord
     */
    public function __construct(
        /** merTaxId */
        public readonly ?string $employerAfm,
        /** merName */
        public readonly ?string $employerName,
        /** The employer's legal form: nomMorfMainCode / nomMorfMainDescr. */
        public readonly ?string $legalFormCode,
        public readonly ?string $legalFormDescr,
        /** The employer's headquarters (the *Main fields). */
        public readonly Workplace $headquarters,
        /** Where the employee works (the *Mpa fields). */
        public readonly Workplace $workplace,
        /** The most specific ΣΤΕΠ-92 occupation code given. */
        public readonly ?string $occupationCode,
        public readonly ?string $occupationDescr,
        /** Καθεστώς απασχόλησης (kathestws). */
        public readonly ?EmploymentType $type,
        /** Αποδοχές (apodoxes). */
        public readonly ?float $salary,
        public readonly ?float $weeklyHours,
        /** dateFrom, time and zone kept. */
        public readonly ?Carbon $from,
        /** dateTo, time and zone kept. */
        public readonly ?Carbon $to,
        public readonly array $raw = [],
        /** The audit_records row of the call, as an array; shared by every item of the same call. */
        public readonly ?array $auditRecord = null,
    ) {
    }

    /**
     * @param array<string, mixed> $record one hist record
     * @param array<string, mixed>|null $auditRecord
     */
    public static function fromRecord(array $record, ?array $auditRecord = null): self
    {
        $text = fn (string $key): ?string => Normalize::field($record, $key);
        $workplace = fn (string $suffix): Workplace => new Workplace(
            number: $text("aa$suffix"),
            activityCode: $text("stakod{$suffix}Code"),
            activityDescr: $text("stakod{$suffix}Descr"),
            address: new Address($text("address$suffix"), $text("addressArithmos$suffix"), $text("tk$suffix"), $text("kalDhmoi{$suffix}Descr")),
            municipalityCode: $text("kalDhmoi{$suffix}Code"),
        );
        $type = $text('kathestws');

        return new self(
            employerAfm: $text('merTaxId'),
            employerName: $text('merName'),
            legalFormCode: $text('nomMorfMainCode'),
            legalFormDescr: $text('nomMorfMainDescr'),
            headquarters: $workplace('Main'),
            workplace: $workplace('Mpa'),
            occupationCode: $text('step6Code') ?? $text('step4Code') ?? $text('step3Code') ?? $text('step2Code'),
            occupationDescr: $text('step6Descr') ?? $text('step4Descr') ?? $text('step3Descr') ?? $text('step2Descr'),
            type: $type !== null && preg_match('/^-?\d+$/', $type) === 1 ? EmploymentType::tryFrom((int) $type) : null,
            salary: Normalize::number($record['apodoxes'] ?? null),
            weeklyHours: Normalize::number($record['weekHours'] ?? null),
            from: Normalize::dateTime($text('dateFrom')),
            to: Normalize::dateTime($text('dateTo')),
            raw: $record,
            auditRecord: $auditRecord,
        );
    }
}
