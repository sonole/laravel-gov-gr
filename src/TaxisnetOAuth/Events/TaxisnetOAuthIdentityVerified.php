<?php

namespace Sonole\GovGr\TaxisnetOAuth\Events;

use Sonole\GovGr\TaxisnetOAuth\Support\TaxisnetOAuthIdentity;

/**
 * Dispatched by handleCallback() once GSIS has verified the user's TAXISnet credentials
 * and userinfo has been fetched. This is before your app has decided whether to let
 * them in. handleCallback() never touches your User model or session, so only use this
 * for logging or telemetry, not to grant access.
 */
class TaxisnetOAuthIdentityVerified
{
    public function __construct(
        public readonly TaxisnetOAuthIdentity $identity,
        public readonly ?string $ip,
    ) {
    }
}
