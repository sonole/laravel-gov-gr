<?php

namespace Sonole\GovGr\Amka\Data;

/**
 * The status indication (ΠΑΑΥΠΑ) of amka2dataE() and amka2dataLE(): whether the number is a regular, active AMKA
 * or one of the temporary / cancelled kinds. amka2data() and data2amka() do not report it.
 */
enum AmkaStatus: string
{
    /** ΕΝΕΡΓΟΣ ΑΜΚΑ: the service sends no status at all for a regular, active AMKA. */
    case Active = '';

    /** ΠΑΑΥΠΑ: Προσωρινός Αριθμός Ασφάλισης και Υγειονομικής Περίθαλψης Αλλοδαπού. */
    case Paaypa = 'Π';

    /** ΠΑΑΥΠΑ ΚΑΤΑΡΓΗΜΕΝΟΣ */
    case PaaypaRevoked = 'ΠΚ';

    /** ΠΑΑΥΠΑ ΑΚΥΡΩΜΕΝΟΣ */
    case PaaypaCancelled = 'ΠΑ';

    /**
     * ΠΑΜΚΑ: issued only for the vaccination of groups entitled to neither an AMKA nor a ΠΑΑΥΠΑ. The spec says
     * records with this status must be excluded from any further processing.
     */
    case Pamka = 'ΠΡ';

    /** ΠΑΜΚΑ ΑΚΥΡΩΜΕΝΟΣ */
    case PamkaCancelled = 'ΠΔ';

    /** ΑΜΚΑ ΑΚΥΡΩΜΕΝΟΣ */
    case Cancelled = 'ΑΑ';

    /** ΠΑΥΠΕΚ */
    case Paypek = 'Κ';

    /** ΠΑΥΠΕΚ ΑΚΥΡΩΜΕΝΟΣ */
    case PaypekCancelled = 'ΚΑ';

    /** ΑΝΕΝΕΡΓΟΣ ΑΜΚΑ */
    case Inactive = '00';

    /** A regular AMKA that is in force. */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /** Cancelled, revoked or inactive, whatever the kind of number. */
    public function isCancelled(): bool
    {
        return in_array($this, [
            self::PaaypaRevoked, self::PaaypaCancelled, self::PamkaCancelled, self::Cancelled, self::PaypekCancelled, self::Inactive,
        ], true);
    }

    /** A vaccination-only ΠΑΜΚΑ record, which the spec says to leave out of any processing. */
    public function isVaccinationOnly(): bool
    {
        return $this === self::Pamka;
    }
}
