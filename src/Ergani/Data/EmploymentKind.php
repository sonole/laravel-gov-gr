<?php

namespace Sonole\GovGr\Ergani\Data;

/** ERGANI's Σχέση Απασχόλησης (getEmploymentRelationship's kind): how long the contract runs. */
enum EmploymentKind: int
{
    /** Άγνωστο */
    case Unknown = -1;

    /** Αορίστου χρόνου */
    case Indefinite = 0;

    /** Ορισμένου χρόνου */
    case FixedTerm = 1;

    /** Έργου (ορισμένου χρόνου) */
    case Project = 2;
}
