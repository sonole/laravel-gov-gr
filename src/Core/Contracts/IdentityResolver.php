<?php

namespace Sonole\GovGr\Core\Contracts;

use Sonole\GovGr\Core\Audit\AuditIdentity;
use Illuminate\Http\Request;

interface IdentityResolver
{
    public function resolve(?Request $request): AuditIdentity;
}
