<?php

namespace Sonole\GovGr\Gemi\Data;

use Sonole\GovGr\Core\Support\Normalize;

/**
 * Who sits on a company's board and who represents it: the result of Gemi::companyBoard() (getCompanyBoardV3).
 * Natural persons are `members`, legal persons `entities`; either list can be empty. `raw` is the whole board
 * record.
 */
final class CompanyBoard
{
    /**
     * @param list<BoardMember> $members
     * @param list<BoardEntity> $entities
     * @param array<string, mixed> $raw
     * @param array<string, mixed>|null $auditRecord
     */
    public function __construct(
        /** The fp list: natural persons. */
        public readonly array $members,
        /** The np list: legal persons. */
        public readonly array $entities,
        public readonly array $raw = [],
        /** The audit_records row of the call, as an array. */
        public readonly ?array $auditRecord = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->members === [] && $this->entities === [];
    }

    /** @return list<BoardMember> */
    public function activeMembers(): array
    {
        return array_values(array_filter($this->members, fn (BoardMember $m) => $m->isActive));
    }

    /** @return list<BoardMember> */
    public function representatives(): array
    {
        return array_values(array_filter($this->members, fn (BoardMember $m) => $m->isRepresentative()));
    }

    /**
     * @param array<string, mixed> $record the getCompanyBoardV3OutputRecord's board
     * @param array<string, mixed>|null $auditRecord
     */
    public static function fromRecord(array $record, ?array $auditRecord = null): self
    {
        return new self(
            members: array_map([BoardMember::class, 'fromRecord'], Normalize::list($record['fp'] ?? null)),
            entities: array_map([BoardEntity::class, 'fromRecord'], Normalize::list($record['np'] ?? null)),
            raw: $record,
            auditRecord: $auditRecord,
        );
    }
}
