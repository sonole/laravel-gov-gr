<?php

namespace Sonole\GovGr\Core\Audit\Resolvers;

use Sonole\GovGr\Core\Contracts\IpResolver;
use Illuminate\Http\Request;

class HeaderIpResolver implements IpResolver
{
    public function __construct(
        private readonly string $header,
        private readonly string $take = 'first',
    ) {
    }

    public function resolve(Request $request): ?string
    {
        $value = $request->header($this->header);

        if (empty($value)) {
            return null;
        }

        if (is_array($value)) {
            $value = implode(',', $value);
        }

        $ips = array_map('trim', explode(',', $value));

        return $this->take === 'last' ? end($ips) : $ips[0];
    }
}
