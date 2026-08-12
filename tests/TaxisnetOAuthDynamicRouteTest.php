<?php

namespace Sonole\GovGr\Tests;

/**
 * Isolated from TaxisnetOAuthTest because the extra route below is only registered once,
 * at boot, from whatever redirect_uri is configured in defineEnvironment(). Setting it
 * mid-test (like TaxisnetOAuthTest does for other assertions) wouldn't re-register routes.
 */
class TaxisnetOAuthDynamicRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('govgr.taxisnet-oauth.redirect_uri', 'https://registered.test/custom/callback/path');
    }

    public function test_a_route_is_registered_automatically_matching_the_redirect_uri_override_path(): void
    {
        $response = $this->get('/custom/callback/path');

        // No session state stored, so TaxisnetOAuthStateMismatchException is expected.
        // The point here is only that it's a 500 (route matched, controller ran), not a
        // 404 (route missing).
        $response->assertStatus(500);
    }

    public function test_the_packages_own_login_callback_route_still_works_alongside_the_override(): void
    {
        $response = $this->get(route('taxisnet-oauth.redirect'));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://pilot.test/oauth2server/oauth/authorize?', $response->headers->get('Location'));
    }
}
