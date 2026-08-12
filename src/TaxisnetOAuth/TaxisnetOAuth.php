<?php

namespace Sonole\GovGr\TaxisnetOAuth;

use Sonole\GovGr\Core\Enums\GovService;
use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Sonole\GovGr\TaxisnetOAuth\Events\TaxisnetOAuthIdentityVerified;
use Sonole\GovGr\TaxisnetOAuth\Exceptions\TaxisnetOAuthException;
use Sonole\GovGr\TaxisnetOAuth\Exceptions\TaxisnetOAuthStateMismatchException;
use Sonole\GovGr\TaxisnetOAuth\Services\OAuthClient;
use Sonole\GovGr\TaxisnetOAuth\Services\StateManager;
use Sonole\GovGr\TaxisnetOAuth\Support\TaxisnetOAuthIdentity;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class TaxisnetOAuth
{
    public function __construct(
        private readonly OAuthClient $client,
        private readonly StateManager $state,
    ) {
    }

    /** Generates and stores a fresh CSRF state, then redirects the browser to GSIS. */
    public function redirect(string $redirectUri): RedirectResponse
    {
        return new RedirectResponse($this->authorizationUrl($this->state->generate(), $redirectUri));
    }

    /**
     * For local testing with bin/taxisnet-local-domain.sh. The redirect_uri override
     * host only resolves back to this app for the callback, since that's the only URL
     * GSIS knows about. If you start the flow from a different host (like localhost),
     * the CSRF state ends up in a session cookie scoped to that host and won't be there
     * when GSIS's callback lands on the override host instead. Looks like a CSRF
     * failure but isn't.
     *
     * Set redirect_uri_resolves_locally to true (only for local testing, never for a
     * real relay/tunnel setup where the override host is a real remote server) and this
     * bounces to the override host first so the state ends up in the right session.
     * Returns null if no bounce is needed.
     */
    public function localDomainBounceUrl(Request $request): ?string
    {
        $override = config('govgr.taxisnet-oauth.redirect_uri');

        if (! config('govgr.taxisnet-oauth.redirect_uri_resolves_locally') || empty($override)) {
            return null;
        }

        $overrideHost = parse_url($override, PHP_URL_HOST);

        // Only compare hosts, not scheme. Caddy talks to this app over plain HTTP, and
        // without trusted proxies configured Laravel has no way to know the real
        // request was HTTPS. Comparing scheme too would loop forever in exactly this setup.
        if ($overrideHost === $request->getHost()) {
            return null;
        }

        return parse_url($override, PHP_URL_SCHEME).'://'.$overrideHost.$request->getRequestUri();
    }

    /** @throws TaxisnetOAuthException */
    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $this->state->store($state);

        return $this->client->authorizationUrl($state, $redirectUri);
    }

    /**
     * Verifies the callback state, exchanges the code for a token, fetches the user's
     * identity, and dispatches TaxisnetOAuthIdentityVerified. Does not touch your User
     * model or session, see TaxisnetOAuthLoginResponder for that.
     *
     * @throws TaxisnetOAuthStateMismatchException|TaxisnetOAuthException|Throwable
     */
    public function handleCallback(Request $request): TaxisnetOAuthIdentity
    {
        if (! $this->state->verify($this->queryString($request, 'state'))) {
            throw new TaxisnetOAuthStateMismatchException(
                'The Taxisnet OAuth callback state did not match this session.'
            );
        }

        $this->state->forget();

        if ($error = $this->queryString($request, 'error')) {
            throw new TaxisnetOAuthException("Taxisnet OAuth authorization was not granted: {$error}");
        }

        $accessToken = $this->client->exchangeCodeForToken($this->queryString($request, 'code'), $request->url());
        try {
            $identity = $this->client->userInfo($accessToken);
        } catch (Throwable $e) {
            throw new TaxisnetOAuthException('Taxisnet OAuth userinfo request failed.', 0, $e);
        }

        $this->logAuditRecord($identity, $request);

        event(new TaxisnetOAuthIdentityVerified($identity, $request->ip()));

        return $identity;
    }

    public function logoutUrl(string $returnUrl): string
    {
        return $this->client->logoutUrl($returnUrl);
    }

    /**
     * Wraps $returnUrl through GSIS's own logout endpoint, so GSIS drops its SSO
     * session too. Skip this and the next login silently reauthenticates without
     * asking for credentials again.
     */
    public function exitRedirectUrl(string $returnUrl): string
    {
        $override = config('govgr.taxisnet-oauth.redirect_uri');

        if (empty($override)) {
            return $this->logoutUrl($returnUrl);
        }

        if (! config('govgr.taxisnet-oauth.redirect_uri_resolves_locally')) {
            return $returnUrl;
        }

        $parts = parse_url($returnUrl);
        $localReturnUrl = parse_url($override, PHP_URL_SCHEME).'://'.parse_url($override, PHP_URL_HOST)
            .($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return $this->logoutUrl($localReturnUrl);
    }

    /**
     * Opt-in via govgr.taxisnet-oauth.audit_enabled: TaxisnetOAuth otherwise never
     * writes to audit_records at all, this is the only place it would. user_email
     * holds $identity->username (or the ΑΦΜ, for a legal-entity login with no
     * username) since a TaxisnetOAuth identity has no email of its own.
     *
     * @throws \RuntimeException
     */
    private function logAuditRecord(TaxisnetOAuthIdentity $identity, Request $request): void
    {
        if (! config('govgr.taxisnet-oauth.audit_enabled', false)) {
            return;
        }

        AuditRecord::record([
            'transaction_date' => Carbon::now(),
            'service' => GovService::TaxisnetOAuth,
            'environment' => config('govgr.taxisnet-oauth.env', 'pilot'),
            'endpoint' => 'taxisnet-oauth.login-callback',
            'user_email' => $identity->username ?? $identity->afm,
            'user_ip' => $request->ip(),
            'status_code' => 200,
        ]);
    }

    /** Query params can come back as arrays (e.g. ?state[]=x); treat those as absent. */
    private function queryString(Request $request, string $key): string
    {
        $value = $request->query($key, '');

        return is_array($value) ? '' : (string) $value;
    }
}
