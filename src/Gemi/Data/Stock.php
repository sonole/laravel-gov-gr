<?php

namespace Sonole\GovGr\Gemi\Data;

use Sonole\GovGr\Core\Support\Normalize;

/** One class of a company's shares / parts (μετοχές / μερίδια): an item of getBasicInfoV3's stocks. */
final class Stock
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        /** How many shares or parts. */
        public readonly ?float $amount,
        public readonly ?float $nominalPrice,
        public readonly ?string $typeId,
        /** "Ονομαστικές, Κοινές", "Κεφαλαιικές", ... */
        public readonly ?string $type,
        public readonly array $raw = [],
    ) {
    }

    /** @param array<string, mixed> $record */
    public static function fromRecord(array $record): self
    {
        return new self(
            amount: Normalize::number($record['amount'] ?? null),
            nominalPrice: Normalize::number($record['nominalPrice'] ?? null),
            typeId: Normalize::field($record, 'stockTypeId'),
            type: Normalize::field($record, 'stockType'),
            raw: $record,
        );
    }
}
