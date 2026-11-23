<?php

namespace Sonole\GovGr\Amka\Data;

use Sonole\GovGr\Core\Support\Normalize;

/**
 * The answer of confirmRelAmka() / confirmRelAmkaMultiple() for one relAmka: whether the AMKA is confirmed as
 * the parent's relation, and the service's own description of the relation ("ΤΕΚΝΟ", ...).
 */
final class RelationshipConfirmation
{
    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed>|null $auditRecord
     */
    public function __construct(
        public readonly ?string $relAmka,
        public readonly bool $confirmed,
        public readonly ?string $info,
        public readonly array $raw = [],
        /** The audit_records row of the call, as an array; shared by every item of a confirmRelAmkaMultiple(). */
        public readonly ?array $auditRecord = null,
    ) {
    }

    /**
     * @param array<string, mixed> $record a confirmRelAmkaOutputRecord, or one item of a confirmList
     * @param array<string, mixed>|null $auditRecord
     */
    public static function fromRecord(array $record, ?string $relAmka = null, ?array $auditRecord = null): self
    {
        return new self(
            relAmka: Normalize::field($record, 'relAmka') ?? $relAmka,
            confirmed: strtolower((string) Normalize::field($record, 'confirmation')) === 'true',
            info: Normalize::field($record, 'info'),
            raw: $record,
            auditRecord: $auditRecord,
        );
    }
}
