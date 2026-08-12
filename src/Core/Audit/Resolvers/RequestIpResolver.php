<?php

namespace Sonole\GovGr\Core\Audit\Resolvers;

use Sonole\GovGr\Core\Contracts\IpResolver;
use Illuminate\Http\Request;

class RequestIpResolver implements IpResolver
{
    public function resolve(Request $request): ?string
    {
        return $request->ip();
    }
}
