<?php

namespace Sonole\GovGr\Core\Audit;

use Sonole\GovGr\Core\Contracts\IdentityResolver;
use Sonole\GovGr\Core\Contracts\IpResolver;
use Illuminate\Http\Request;
use LogicException;

/** Identity for an authenticated web request. DefaultIdentityResolver only routes here once $request and $request->user() are both present. */
class RequestIdentityResolver implements IdentityResolver
{
    public function __construct(private readonly IpResolver $ipResolver)
    {
    }

    public function resolve(?Request $request): AuditIdentity
    {
        if (! $request || ! $request->user()) {
            throw new LogicException('RequestIdentityResolver requires an authenticated request. Route through DefaultIdentityResolver instead omitting $request.');
        }

        return new AuditIdentity(
            email: $request->user()->email,
            ip: $this->ipResolver->resolve($request),
            userId: $request->user()->getKey(),

        );
    }
}