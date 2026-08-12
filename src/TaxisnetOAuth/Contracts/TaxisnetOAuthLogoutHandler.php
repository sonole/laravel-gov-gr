<?php

namespace Sonole\GovGr\TaxisnetOAuth\Contracts;

use Illuminate\Http\Request;

/**
 * Ends this app's own session before the browser is bounced through GSIS's logout
 * endpoint. Almost every app just needs the standard guard-logout/session-invalidate
 * boilerplate (the default), but bind your own implementation in your own service
 * provider if you log out a non-default guard, revoke tokens, etc:
 *
 *   $this->app->bind(TaxisnetOAuthLogoutHandler::class, MyLogoutHandler::class);
 */
interface TaxisnetOAuthLogoutHandler
{
    public function handle(Request $request): void;
}
