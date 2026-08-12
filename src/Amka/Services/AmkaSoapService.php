<?php

namespace Sonole\GovGr\Amka\Services;

use DateTime;
use Sonole\GovGr\Core\Services\AuditedSoapService;

/** Shared by both AMKA SOAP services */
abstract class AmkaSoapService extends AuditedSoapService
{
    /** AMKA returns the whole record (output plus callSequenceId/errorRecord siblings), not one drilled-down output key. */
    protected function outputRecordKey(string $operation): ?string
    {
        return null;
    }

    /** Maps the two documented WS-Security fault strings */
    protected function faultMessage(string $faultString): string
    {
        return match (true) {
            str_contains($faultString, 'A security error was encountered when verifying the message') => 'AMKA rejected the request: invalid username or password.',
            str_contains($faultString, 'SupportingTokens') && str_contains($faultString, 'UsernameToken') => 'AMKA rejected the request: no WS-Security credentials were sent.',
            $faultString === '' => 'AMKA SOAP fault.',
            default => $faultString,
        };
    }

    /** Rejects malformed strings and impossible calendar dates (e.g. 31/02/2020) via a format round-trip. */
    protected static function isValidBirthDate(string $date): bool
    {
        $parsed = DateTime::createFromFormat('!d/m/Y', $date);

        return $parsed !== false && $parsed->format('d/m/Y') === $date;
    }
}
