<?php

namespace Sonole\GovGr\Gemi\Data;

use Sonole\GovGr\Core\Support\Normalize;

/** A company's capital: getBasicInfoV3's capital record. */
final class Capital
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        /** capitalStock */
        public readonly ?float $amount,
        public readonly ?string $currency,
        /** Εξωκεφαλαιικές εισφορές (ecsokefalaiikes), ΙΚΕ only. */
        public readonly ?float $nonCapitalContributions,
        /** Εγγυητικές εισφορές (eggiitikes), ΙΚΕ only. */
        public readonly ?float $guaranteeContributions,
        public readonly array $raw = [],
    ) {
    }

    /** @param array<string, mixed> $record */
    public static function fromRecord(array $record): self
    {
        return new self(
            amount: Normalize::number($record['capitalStock'] ?? null),
            currency: Normalize::field($record, 'currency'),
            nonCapitalContributions: Normalize::number($record['ecsokefalaiikes'] ?? null),
            guaranteeContributions: Normalize::number($record['eggiitikes'] ?? null),
            raw: $record,
        );
    }
}
