<?php

namespace Sonole\GovGr\Core\Audit\Resolvers;

use Sonole\GovGr\Core\Contracts\IpResolver;
use Illuminate\Http\Request;

class ChainIpResolver implements IpResolver
{
    /** @param IpResolver[] $resolvers */
    public function __construct(private readonly array $resolvers)
    {
    }

    public function resolve(Request $request): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $ip = $resolver->resolve($request);

            if (! empty($ip)) {
                return $ip;
            }
        }

        return null;
    }
}
