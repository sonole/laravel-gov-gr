<?php

namespace Sonole\GovGr\Core\Audit;

final class AuditIdentity
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $ip,
        public readonly ?int $userId = null,
    ) {
    }
}
