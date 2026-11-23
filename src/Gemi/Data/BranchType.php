<?php

namespace Sonole\GovGr\Gemi\Data;

/**
 * getBasicInfoV3's branchType.branchTypeId: what kind of branch a GEMI record is. A parent company has no
 * branch type at all (Company::$branchType is null).
 */
enum BranchType: string
{
    /** Υποκατάστημα Ελληνικής Επιχείρησης */
    case GreekCompany = '1';

    /** Υποκατάστημα Αλλοδαπής Ευρωπαϊκής Ένωσης */
    case EuCompany = '2';

    /** Υποκατάστημα Αλλοδαπής εκτός ΕΕ */
    case NonEuCompany = '3';

    /** Υποκατάστημα Αλλοδαπής - άγνωστη προέλευση */
    case UnknownOrigin = '4';
}
