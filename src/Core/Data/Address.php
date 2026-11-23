<?php

namespace Sonole\GovGr\Core\Data;

final class Address
{
    public function __construct(
        public readonly ?string $street = null,
        public readonly ?string $number = null,
        public readonly ?string $zip = null,
        public readonly ?string $area = null,
        public readonly ?string $country = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return !filled($this->street) && !filled($this->zip) && !filled($this->area);
    }
}
