<?php

namespace Sonole\GovGr\TaxisnetOAuth\Http\Controllers;

use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLoginResponder;
use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLogoutHandler;
use Sonole\GovGr\TaxisnetOAuth\TaxisnetOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaxisnetOAuthController
{
    public function __construct(
        private readonly TaxisnetOAuth $taxisnetOAuth,
        private readonly TaxisnetOAuthLoginResponder $responder,
        private readonly TaxisnetOAuthLogoutHandler $logoutHandler,
    ) {
    }

    public function redirect(Request $request): RedirectResponse
    {
        if ($bounceUrl = $this->taxisnetOAuth->localDomainBounceUrl($request)) {
            return redirect()->away($bounceUrl);
        }

        return $this->taxisnetOAuth->redirect(route('taxisnet-oauth.callback'));
    }

    public function callback(Request $request): Response
    {
        $identity = $this->taxisnetOAuth->handleCallback($request);

        return $this->responder->respond($identity, $request);
    }

    /**
     * Ends this app's own session, then bounces the browser through GSIS's logout
     * endpoint to clear TAXISnet's SSO session too.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->logoutHandler->handle($request);

        return redirect()->away(
            $this->taxisnetOAuth->exitRedirectUrl(url()->to(config('govgr.taxisnet-oauth.logout_redirect_to', '/')))
        );
    }
}
