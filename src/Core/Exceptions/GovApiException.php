<?php

namespace Sonole\GovGr\Core\Exceptions;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Http\Models\AuditRecord;

/** @phpstan-consistent-constructor */
class GovApiException extends \RuntimeException
{
    private ?AuditRecord $auditRecord = null;

    private ?int $httpStatusCode = null;

    private ?GovService $service = null;

    /** Set by newException()/newAuthenticationException() at throw time. Lets callers tell which API failed without needing a per-module exception type. */
    public function withService(GovService $service): static
    {
        $this->service = $service;

        return $this;
    }

    /** A copy of $previous as this exception type, with its service, audit record and HTTP status carried over. */
    public static function wrapping(self $previous): static
    {
        $exception = new static($previous->getMessage(), 0, $previous);

        if ($previous->service !== null) {
            $exception->withService($previous->service);
        }

        return $exception->withAuditContext($previous->auditRecord)->withHttpStatus($previous->httpStatusCode);
    }

    public function service(): ?GovService
    {
        return $this->service;
    }

    /** Set by BaseGovService::logged() before rethrowing, so callers can correlate a failure with its audit_records row. */
    public function withAuditContext(?AuditRecord $auditRecord): static
    {
        $this->auditRecord = $auditRecord;

        return $this;
    }

    public function auditRecord(): ?AuditRecord
    {
        return $this->auditRecord;
    }

    /** Set by each package's unwrap()-equivalent, so logged() can still persist the HTTP status on the audit record even when unwrapping the response is what threw. */
    public function withHttpStatus(?int $httpStatusCode): static
    {
        $this->httpStatusCode = $httpStatusCode;

        return $this;
    }

    public function httpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }
}
