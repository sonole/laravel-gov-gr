<?php

namespace Sonole\GovGr\Ergani\Data;

/**
 * ERGANI's Καθεστώς Απασχόλησης: getEmploymentRelationship's typos and getEmploymentHistoryByPeriod's kathestws
 * (which never sends Unknown).
 */
enum EmploymentType: int
{
    /** Άγνωστο */
    case Unknown = -1;

    /** Πλήρης */
    case FullTime = 0;

    /** Μερική */
    case PartTime = 1;

    /** Εκ περιτροπής */
    case Rotating = 2;
}
