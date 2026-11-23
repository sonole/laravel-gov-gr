<?php

namespace Sonole\GovGr\Ergani\Data;

use Sonole\GovGr\Core\Support\Normalize;

/**
 * Someone's employment on a given date: the result of Ergani::employmentRelationship() (getEmploymentRelationship's
 * sev record). `kind` and `type` are null when the service sent a code outside the documented ones (see ->raw).
 */
final class EmploymentStatus
{
    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed>|null $auditRecord
     */
    public function __construct(
        /** Σχέση απασχόλησης (kind). */
        public readonly ?EmploymentKind $kind,
        /** Καθεστώς απασχόλησης (typos). */
        public readonly ?EmploymentType $type,
        public readonly array $raw = [],
        /** The audit_records row of the call, as an array. */
        public readonly ?array $auditRecord = null,
    ) {
    }

    /**
     * @param array<string, mixed> $record the sev record
     * @param array<string, mixed>|null $auditRecord
     */
    public static function fromRecord(array $record, ?array $auditRecord = null): self
    {
        return new self(
            kind: self::code($record, 'kind') === null ? null : EmploymentKind::tryFrom(self::code($record, 'kind')),
            type: self::code($record, 'typos') === null ? null : EmploymentType::tryFrom(self::code($record, 'typos')),
            raw: $record,
            auditRecord: $auditRecord,
        );
    }

    /** @param array<string, mixed> $record */
    private static function code(array $record, string $key): ?int
    {
        $text = Normalize::field($record, $key);

        return $text !== null && preg_match('/^-?\d+$/', $text) === 1 ? (int) $text : null;
    }
}
