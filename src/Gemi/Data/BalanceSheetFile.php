<?php

namespace Sonole\GovGr\Gemi\Data;

use Sonole\GovGr\Core\Support\Normalize;

/** A filed document of a balance sheet: an item of a companyBalancesheets entry's files. */
final class BalanceSheetFile
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        /** fileId, what the document endpoint takes. */
        public readonly string $id,
        /** arGemi */
        public readonly ?string $gemiNumber,
        /** "balancesheet", ... */
        public readonly ?string $type,
        public readonly array $raw = [],
    ) {
    }

    /** @param array<string, mixed> $record */
    public static function fromRecord(array $record): self
    {
        return new self(
            id: (string) Normalize::field($record, 'fileId'),
            gemiNumber: Normalize::field($record, 'arGemi'),
            type: Normalize::field($record, 'type'),
            raw: $record,
        );
    }
}
