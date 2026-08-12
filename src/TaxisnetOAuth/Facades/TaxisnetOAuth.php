<?php

namespace Sonole\GovGr\TaxisnetOAuth\Facades;

use Sonole\GovGr\TaxisnetOAuth\Support\TaxisnetOAuthIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static RedirectResponse redirect(string $redirectUri)
 * @method static string authorizationUrl(string $state, string $redirectUri)
 * @method static TaxisnetOAuthIdentity handleCallback(Request $request)
 * @method static string logoutUrl(string $returnUrl)
 * @method static string exitRedirectUrl(string $returnUrl)
 *
 * @see \Sonole\GovGr\TaxisnetOAuth\TaxisnetOAuth
 */
class TaxisnetOAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sonole\GovGr\TaxisnetOAuth\TaxisnetOAuth::class;
    }
}
