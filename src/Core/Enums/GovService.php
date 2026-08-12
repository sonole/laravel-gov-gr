<?php

namespace Sonole\GovGr\Core\Enums;

/** Every service()/AuditRecord::service value this package writes. */
enum GovService: string
{
    case AmkaInfo = 'amka_info';
    case AmkaRelationship = 'amka_relationship';
    case BankConfirm = 'bank_confirm';
    case Ergani = 'ergani';
    case Gemi = 'gemi';
    case TaxRegistry = 'tax_registry';
    case TaxRegistryPublic = 'tax_registry_public';
    case TaxisnetOAuth = 'taxisnet_oauth';
}
