<?php

namespace Sonole\GovGr\TaxisnetOAuth\Contracts;

use Sonole\GovGr\TaxisnetOAuth\Support\TaxisnetOAuthIdentity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides what happens once GSIS has verified a user's identity. This is the one piece
 * that's actually app-specific: look up the AFM in your own users table, check
 * isLegalEntity(), call Auth::login() yourself, etc. Bind your own implementation in
 * your own service provider to replace the package default:
 *
 *   $this->app->bind(TaxisnetOAuthLoginResponder::class, MyLoginResponder::class);
 *
 * See the package README for a full example.
 */
interface TaxisnetOAuthLoginResponder
{
    public function respond(TaxisnetOAuthIdentity $identity, Request $request): Response;
}
