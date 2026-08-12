<?php

namespace Sonole\GovGr\Core\Contracts;

use Illuminate\Http\Request;

interface IpResolver
{
    public function resolve(Request $request): ?string;
}
