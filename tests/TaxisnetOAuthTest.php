<?php

namespace Sonole\GovGr\Tests;

use Sonole\GovGr\Core\Http\Models\AuditRecord;
use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLoginResponder;
use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLogoutHandler;
use Sonole\GovGr\TaxisnetOAuth\Events\TaxisnetOAuthIdentityVerified;
use Sonole\GovGr\TaxisnetOAuth\Exceptions\TaxisnetOAuthException;
use Sonole\GovGr\TaxisnetOAuth\Exceptions\TaxisnetOAuthStateMismatchException;
use Sonole\GovGr\TaxisnetOAuth\Facades\TaxisnetOAuth;
use Sonole\GovGr\TaxisnetOAuth\Support\TaxisnetOAuthIdentity;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class TaxisnetOAuthTest extends TestCase
{
    private const PHYSICAL_PERSON_XML = <<<'XML'
        <auth>
            <userinfo taxid="660074100" lastname="ΠΑΠΑΔΟΠΟΥΛΟΣ" firstname="ΓΙΩΡΓΟΣ" fathername="ΝΙΚΟΛΑΟΣ" mothername="ΜΑΡΙΑ" userid="Testked1" birthyear="1980" />
        </auth>
        XML;

    private const LEGAL_ENTITY_XML = <<<'XML'
        <auth>
            <userinfo taxid="098120800" lastname="ΕΤΑΙΡΙΑ ΑΕ" firstname="null" fathername="null" mothername="null" userid="Nomked1" birthyear="null" />
        </auth>
        XML;

    private function callbackRequest(array $query): Request
    {
        return Request::create('https://app.test/taxisnet-oauth/login-callback', 'GET', $query);
    }

    private function fakeSuccessfulExchange(string $userInfoXml = self::PHYSICAL_PERSON_XML): void
    {
        Http::fake([
            'pilot.test/oauth2server/oauth/token*' => Http::response(['access_token' => 'abc123'], 200),
            'pilot.test/oauth2server/userinfo*' => Http::response($userInfoXml, 200, ['Content-Type' => 'text/xml']),
        ]);
    }

    public function test_authorization_url_has_no_scope_and_uses_the_given_redirect_uri(): void
    {
        $url = TaxisnetOAuth::authorizationUrl('the-state', 'https://app.test/callback');

        $this->assertStringStartsWith('https://pilot.test/oauth2server/oauth/authorize?', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('client_id=pilot-client-id', $url);
        $this->assertStringContainsString('redirect_uri='.urlencode('https://app.test/callback'), $url);
        $this->assertStringContainsString('state=the-state', $url);
        $this->assertStringNotContainsString('scope=', $url);
    }

    public function test_authorization_url_uses_the_redirect_uri_override_when_configured(): void
    {
        config(['govgr.taxisnet-oauth.redirect_uri' => 'https://registered.test/callback']);

        $url = TaxisnetOAuth::authorizationUrl('the-state', 'https://app.test/callback');

        $this->assertStringContainsString('redirect_uri='.urlencode('https://registered.test/callback'), $url);
        $this->assertStringNotContainsString(urlencode('https://app.test/callback'), $url);
    }

    public function test_redirect_stores_state_in_session_and_points_at_the_authorize_endpoint(): void
    {
        $response = TaxisnetOAuth::redirect('https://app.test/callback');

        $this->assertNotEmpty(session('taxisnet_oauth_state'));
        $this->assertStringContainsString('state='.session('taxisnet_oauth_state'), $response->getTargetUrl());
    }

    public function test_local_domain_bounce_url_is_null_when_flag_is_off(): void
    {
        config(['govgr.taxisnet-oauth.redirect_uri' => 'https://registered.test/callback']);

        $request = Request::create('http://localhost:8000/taxisnet-oauth/login');

        $this->assertNull(TaxisnetOAuth::localDomainBounceUrl($request));
    }

    public function test_local_domain_bounce_url_is_null_when_no_override_configured(): void
    {
        config(['govgr.taxisnet-oauth.redirect_uri_resolves_locally' => true]);

        $request = Request::create('http://localhost:8000/taxisnet-oauth/login');

        $this->assertNull(TaxisnetOAuth::localDomainBounceUrl($request));
    }

    public function test_local_domain_bounce_url_is_null_when_already_on_the_override_host(): void
    {
        config([
            'govgr.taxisnet-oauth.redirect_uri' => 'https://registered.test/callback',
            'govgr.taxisnet-oauth.redirect_uri_resolves_locally' => true,
        ]);

        $request = Request::create('https://registered.test/taxisnet-oauth/login');

        $this->assertNull(TaxisnetOAuth::localDomainBounceUrl($request));
    }

    public function test_local_domain_bounce_url_targets_the_override_host_preserving_path_and_query(): void
    {
        config([
            'govgr.taxisnet-oauth.redirect_uri' => 'https://registered.test/callback',
            'govgr.taxisnet-oauth.redirect_uri_resolves_locally' => true,
        ]);

        $request = Request::create('http://localhost:8000/taxisnet-oauth/login', 'GET', ['foo' => 'bar']);

        $this->assertSame(
            'https://registered.test/taxisnet-oauth/login?foo=bar',
            TaxisnetOAuth::localDomainBounceUrl($request)
        );
    }

    public function test_redirect_route_bounces_to_the_override_host_when_resolves_locally_is_enabled(): void
    {
        config([
            'govgr.taxisnet-oauth.redirect_uri' => 'https://registered.test/taxisnet-oauth/login-callback',
            'govgr.taxisnet-oauth.redirect_uri_resolves_locally' => true,
        ]);

        $response = $this->get('http://localhost/taxisnet-oauth/login');

        $response->assertRedirect('https://registered.test/taxisnet-oauth/login');
    }

    public function test_handle_callback_exchanges_code_form_encoded_by_default_and_returns_identity(): void
    {
        session(['taxisnet_oauth_state' => 'the-state']);
        $this->fakeSuccessfulExchange();

        $identity = TaxisnetOAuth::handleCallback($this->callbackRequest(['state' => 'the-state', 'code' => 'auth-code-123']));

        $this->assertSame('660074100', $identity->afm);
        $this->assertSame('ΓΙΩΡΓΟΣ', $identity->firstName);
        $this->assertSame('Testked1', $identity->username);
        $this->assertFalse($identity->isLegalEntity());

        Http::assertSent(function (ClientRequest $r) {
            if (! str_contains($r->url(), 'oauth/token')) {
                return false;
            }

            return $r->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
                && $r->data()['grant_type'] === 'authorization_code'
                && $r->data()['code'] === 'auth-code-123'
                && $r->data()['client_id'] === 'pilot-client-id'
                && $r->data()['client_secret'] === 'pilot-client-secret'
                && $r->data()['scope'] === 'read'
                && $r->data()['redirect_uri'] === 'https://app.test/taxisnet-oauth/login-callback';
        });
    }

    public function test_handle_callback_throws_when_token_exchange_fails(): void
    {
        session(['taxisnet_oauth_state' => 'the-state']);
        Http::fake(['pilot.test/oauth2server/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->expectException(TaxisnetOAuthException::class);

        TaxisnetOAuth::handleCallback($this->callbackRequest(['state' => 'the-state', 'code' => 'bad-code']));
    }

    public function test_handle_callback_does_not_write_an_audit_record_by_default(): void
    {
        session(['taxisnet_oauth_state' => 'the-state']);
        $this->fakeSuccessfulExchange();

        TaxisnetOAuth::handleCallback($this->callbackRequest(['state' => 'the-state', 'code' => 'auth-code-123']));

        $this->assertSame(0, AuditRecord::count());
    }

    public function test_handle_callback_writes_an_audit_record_when_enabled(): void
    {
        config(['govgr.taxisnet-oauth.audit_enabled' => true]);
        session(['taxisnet_oauth_state' => 'the-state']);
        $this->fakeSuccessfulExchange();

        TaxisnetOAuth::handleCallback($this->callbackRequest(['state' => 'the-state', 'code' => 'auth-code-123']));

        $this->assertSame(1, AuditRecord::count());

        $record = AuditRecord::query()->first();
        $this->assertSame('taxisnet_oauth', $record->service->value);
        $this->assertSame('Testked1', $record->user_email);
        $this->assertSame(200, $record->status_code);
        $this->assertNotNull($record->protocol);
    }

    public function test_identity_from_attributes_trims_whitespace_from_every_field(): void
    {
        $identity = TaxisnetOAuthIdentity::fromAttributes([
            'taxid' => ' 660074100 ',
            'lastname' => ' ΠΑΠΑΔΟΠΟΥΛΟΣ ',
            'firstname' => ' ΓΙΩΡΓΟΣ ',
            'fathername' => ' ΝΙΚΟΛΑΟΣ ',
            'mothername' => ' ΜΑΡΙΑ ',
            'userid' => ' Testked1 ',
            'birthyear' => ' 1980 ',
        ]);

        $this->assertSame('660074100', $identity->afm);
        $this->assertSame('ΠΑΠΑΔΟΠΟΥΛΟΣ', $identity->lastName);
        $this->assertSame('ΓΙΩΡΓΟΣ', $identity->firstName);
        $this->assertSame('ΝΙΚΟΛΑΟΣ', $identity->fatherName);
        $this->assertSame('ΜΑΡΙΑ', $identity->motherName);
        $this->assertSame('Testked1', $identity->username);
        $this->assertSame('1980', $identity->birthYear);
    }

    public function test_identity_from_attributes_treats_whitespace_only_values_as_null(): void
    {
        $identity = TaxisnetOAuthIdentity::fromAttributes([
            'taxid' => '660074100',
            'firstname' => '   ',
        ]);

        $this->assertNull($identity->firstName);
    }

    public function test_handle_callback_normalizes_the_null_sentinel_for_a_legal_entity(): void
    {
        session(['taxisnet_oauth_state' => 'the-state']);
        $this->fakeSuccessfulExchange(self::LEGAL_ENTITY_XML);

        $identity = TaxisnetOAuth::handleCallback($this->callbackRequest(['state' => 'the-state', 'code' => 'auth-code-123']));

        $this->assertSame('098120800', $identity->afm);
        $this->assertNull($identity->firstName);
        $this->assertNull($identity->fatherName);
        $this->assertNull($identity->birthYear);
        $this->assertTrue($identity->isLegalEntity());
        $this->assertSame('ΕΤΑΙΡΙΑ ΑΕ', $identity->companyName());
    }

    public function test_handle_callback_throws_state_mismatch_and_sends_no_requests_when_state_is_absent(): void
    {
        Http::fake();

        $this->expectException(TaxisnetOAuthStateMismatchException::class);

        TaxisnetOAuth::handleCallback($this->callbackRequest(['state' => 'unexpected', 'code' => 'auth-code-123']));

        Http::assertNothingSent();
    }

    public function test_handle_callback_forgets_the_session_state_after_success(): void
    {
        session(['taxisnet_oauth_state' => 'the-state']);
        $this->fakeSuccessfulExchange();

        TaxisnetOAuth::handleCallback($this->callbackRequest(['state' => 'the-state', 'code' => 'auth-code-123']));

        $this->assertNull(session('taxisnet_oauth_state'));
    }

    public function test_handle_callback_dispatches_taxisnet_oauth_identity_verified_on_success(): void
    {
        Event::fake();
        session(['taxisnet_oauth_state' => 'the-state']);
        $this->fakeSuccessfulExchange();

        TaxisnetOAuth::handleCallback($this->callbackRequest(['state' => 'the-state', 'code' => 'auth-code-123']));

        Event::assertDispatched(TaxisnetOAuthIdentityVerified::class, fn ($event) => $event->identity->afm === '660074100');
    }

    public function test_logout_url_builds_the_expected_format(): void
    {
        $url = TaxisnetOAuth::logoutUrl('https://app.test/');

        $this->assertSame('https://pilot.test/oauth2server/logout/pilot-client-id/?url='.rawurlencode('https://app.test/'), $url);
    }

    public function test_exit_redirect_url_wraps_through_logout_by_default(): void
    {
        $this->assertSame(TaxisnetOAuth::logoutUrl('https://app.test/'), TaxisnetOAuth::exitRedirectUrl('https://app.test/'));
    }

    public function test_exit_redirect_url_skips_the_logout_wrap_when_the_dev_override_is_active(): void
    {
        config(['govgr.taxisnet-oauth.redirect_uri' => 'https://registered.test/callback']);

        $this->assertSame('https://app.test/', TaxisnetOAuth::exitRedirectUrl('https://app.test/'));
    }

    public function test_exit_redirect_url_wraps_via_the_override_host_when_resolves_locally_is_enabled(): void
    {
        config([
            'govgr.taxisnet-oauth.redirect_uri' => 'https://registered.test/callback',
            'govgr.taxisnet-oauth.redirect_uri_resolves_locally' => true,
        ]);

        $this->assertSame(
            TaxisnetOAuth::logoutUrl('https://registered.test/'),
            TaxisnetOAuth::exitRedirectUrl('https://app.test/')
        );
    }

    public function test_exit_redirect_url_preserves_path_and_query_when_resolves_locally_is_enabled(): void
    {
        config([
            'govgr.taxisnet-oauth.redirect_uri' => 'https://registered.test/callback',
            'govgr.taxisnet-oauth.redirect_uri_resolves_locally' => true,
        ]);

        $this->assertSame(
            TaxisnetOAuth::logoutUrl('https://registered.test/goodbye?foo=bar'),
            TaxisnetOAuth::exitRedirectUrl('https://app.test/goodbye?foo=bar')
        );
    }

    public function test_missing_credentials_are_rejected_before_any_http_call(): void
    {
        config(['govgr.taxisnet-oauth.pilot.client_secret' => '']);
        Http::fake();

        try {
            TaxisnetOAuth::authorizationUrl('the-state', 'https://app.test/callback');
            $this->fail('Expected a TaxisnetOAuthException.');
        } catch (TaxisnetOAuthException $e) {
            $this->assertStringContainsString('TAXISNET_OAUTH_PILOT', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_routes_enabled_defaults_to_true(): void
    {
        $this->assertTrue(config('govgr.taxisnet-oauth.routes_enabled'));
    }

    public function test_redirect_route_redirects_to_the_gsis_authorize_endpoint(): void
    {
        $response = $this->get(route('taxisnet-oauth.redirect'));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://pilot.test/oauth2server/oauth/authorize?', $response->headers->get('Location'));
    }

    public function test_callback_route_throws_state_mismatch_when_state_does_not_belong_to_this_session(): void
    {
        $this->withoutExceptionHandling();

        $this->expectException(TaxisnetOAuthStateMismatchException::class);

        $this->get(route('taxisnet-oauth.callback', ['state' => 'unexpected', 'code' => 'auth-code-123']));
    }

    public function test_callback_route_returns_the_default_json_responder_on_success(): void
    {
        $this->fakeSuccessfulExchange();

        $response = $this->withSession(['taxisnet_oauth_state' => 'the-state'])
            ->get(route('taxisnet-oauth.callback', ['state' => 'the-state', 'code' => 'auth-code-123']));

        $response->assertOk();
        $response->assertJson(['afm' => '660074100', 'is_legal_entity' => false]);
    }

    public function test_callback_route_uses_a_custom_bound_responder(): void
    {
        $this->app->bind(TaxisnetOAuthLoginResponder::class, fn () => new class implements TaxisnetOAuthLoginResponder
        {
            public function respond(TaxisnetOAuthIdentity $identity, Request $request): Response
            {
                return response('custom:'.$identity->afm);
            }
        });
        $this->fakeSuccessfulExchange();

        $response = $this->withSession(['taxisnet_oauth_state' => 'the-state'])
            ->get(route('taxisnet-oauth.callback', ['state' => 'the-state', 'code' => 'auth-code-123']));

        $response->assertOk();
        $response->assertSeeText('custom:660074100');
    }

    public function test_logout_redirect_defaults_are_the_default_guard_and_home(): void
    {
        $this->assertNull(config('govgr.taxisnet-oauth.logout_guard'));
        $this->assertSame('/', config('govgr.taxisnet-oauth.logout_redirect_to'));
    }

    public function test_logout_route_calls_the_bound_logout_handler_then_redirects_through_gsis_logout(): void
    {
        $spy = new class implements TaxisnetOAuthLogoutHandler
        {
            public bool $called = false;

            public function handle(Request $request): void
            {
                $this->called = true;
            }
        };
        $this->app->instance(TaxisnetOAuthLogoutHandler::class, $spy);

        $response = $this->post(route('taxisnet-oauth.logout'));

        $this->assertTrue($spy->called);
        $response->assertRedirect('https://pilot.test/oauth2server/logout/pilot-client-id/?url='.rawurlencode(url('/')));
    }

    public function test_logout_route_skips_the_gsis_wrap_when_the_dev_override_is_active(): void
    {
        config(['govgr.taxisnet-oauth.redirect_uri' => 'https://registered.test/callback']);

        $response = $this->post(route('taxisnet-oauth.logout'));

        $response->assertRedirect(url('/'));
    }
}
