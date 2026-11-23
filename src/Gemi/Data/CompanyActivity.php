<?php

namespace Sonole\GovGr\Gemi\Data;

use Carbon\Carbon;
use Sonole\GovGr\Core\Support\Normalize;

/** One of a company's activities (ΚΑΔ): an item of getBasicInfoV3's businessSubjects. */
final class CompanyActivity
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        /** The registry's own id of the activity (professionId). */
        public readonly ?string $id,
        /** The ΚΑΔ code (professionCode). */
        public readonly ?string $code,
        public readonly ?string $descr,
        public readonly ?Carbon $startDate,
        public readonly ?Carbon $endDate,
        public readonly array $raw = [],
    ) {
    }

    public function isActive(): bool
    {
        return $this->endDate === null;
    }

    /** @param array<string, mixed> $record */
    public static function fromRecord(array $record): self
    {
        $text = fn (string $key): ?string => Normalize::field($record, $key);

        return new self(
            id: $text('professionId'),
            code: $text('professionCode'),
            descr: $text('professionDescr'),
            startDate: Normalize::date($text('startDate')),
            endDate: Normalize::date($text('endDate')),
            raw: $record,
        );
    }
}
