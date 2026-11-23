<?php

namespace Sonole\GovGr\Ergani\Data;

use Sonole\GovGr\Core\Data\Address;

/**
 * An employer's establishment as getEmploymentHistoryByPeriod describes it: the headquarters (*Main fields) or
 * the branch the employee works at (*Mpa fields).
 */
final class Workplace
{
    public function __construct(
        /** The establishment's serial number (aa); "0" is the headquarters. */
        public readonly ?string $number,
        /** The ΚΑΔ of the establishment (stakod*Code). */
        public readonly ?string $activityCode,
        public readonly ?string $activityDescr,
        /** address, addressArithmos, tk and the Kallikratis municipality. */
        public readonly Address $address,
        /** kalDhmoi*Code */
        public readonly ?string $municipalityCode,
    ) {
    }
}
