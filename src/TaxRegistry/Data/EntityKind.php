<?php

namespace Sonole\GovGr\TaxRegistry\Data;

/** The registry's iNiFlag: 1 is a natural person, 2 a legal entity. */
enum EntityKind: string
{
    case NaturalPerson = '1';
    case LegalEntity = '2';
}
