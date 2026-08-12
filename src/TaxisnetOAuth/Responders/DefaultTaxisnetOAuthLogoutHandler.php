<?php

namespace Sonole\GovGr\TaxisnetOAuth\Responders;

use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLogoutHandler;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Default behavior: logs out the configured guard (or default guard if unset) and
 * invalidates the session. Same as what Fortify's own logout action does. See
 * TaxisnetOAuthLogoutHandler for how to override it.
 */
class DefaultTaxisnetOAuthLogoutHandler implements TaxisnetOAuthLogoutHandler
{
    public function handle(Request $request): void
    {
        $guard = Auth::guard(config('govgr.taxisnet-oauth.logout_guard'));

        if ($guard instanceof StatefulGuard) {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
