<?php

namespace Sonole\GovGr\Core\Audit;

use Sonole\GovGr\Core\Contracts\IdentityResolver;
use Illuminate\Http\Request;

/**
 * Routes to RequestIdentityResolver for an authenticated request,
 * or SystemIdentityResolver otherwise (eg. queued jobs, scheduled commands, console-triggered lookups).
 */
class DefaultIdentityResolver implements IdentityResolver
{
    public function __construct(
        private readonly RequestIdentityResolver $requestResolver,
        private readonly SystemIdentityResolver $systemResolver,
    ) {
    }

    public function resolve(?Request $request): AuditIdentity
    {
        return $request && $request->user()
            ? $this->requestResolver->resolve($request)
            : $this->systemResolver->resolve($request);
    }
}