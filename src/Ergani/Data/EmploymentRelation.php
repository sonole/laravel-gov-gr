<?php

namespace Sonole\GovGr\Ergani\Data;

use Sonole\GovGr\Core\Support\Normalize;

/**
 * Someone's current job as ERGANI has it: the result of Ergani::employeeRelation() (getEmployeeRelation's
 * current record). `raw` is that record as returned.
 */
final class EmploymentRelation
{
    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed>|null $auditRecord
     */
    public function __construct(
        /** employerTaxNumber */
        public readonly ?string $employerAfm,
        public readonly ?string $employerName,
        /** employeeTaxNumber */
        public readonly ?string $employeeAfm,
        /** Μηνιαίες αποδοχές (employeeSalary). */
        public readonly ?float $monthlySalary,
        /** Ωρομίσθιο (employeeHourSalary). */
        public readonly ?float $hourlyWage,
        /** employeeWeekHours */
        public readonly ?float $weeklyHours,
        public readonly array $raw = [],
        /** The audit_records row of the call, as an array. */
        public readonly ?array $auditRecord = null,
    ) {
    }

    /**
     * @param array<string, mixed> $record the current record
     * @param array<string, mixed>|null $auditRecord
     */
    public static function fromRecord(array $record, ?array $auditRecord = null): self
    {
        $text = fn (string $key): ?string => Normalize::field($record, $key);

        return new self(
            employerAfm: $text('employerTaxNumber'),
            employerName: $text('employerName'),
            employeeAfm: $text('employeeTaxNumber'),
            monthlySalary: Normalize::number($record['employeeSalary'] ?? null),
            hourlyWage: Normalize::number($record['employeeHourSalary'] ?? null),
            weeklyHours: Normalize::number($record['employeeWeekHours'] ?? null),
            raw: $record,
            auditRecord: $auditRecord,
        );
    }
}
