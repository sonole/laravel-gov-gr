<?php

namespace Sonole\GovGr\TaxisnetOAuth\Services;

use Sonole\GovGr\Core\Http\GovHttpClient;
use Sonole\GovGr\TaxisnetOAuth\Exceptions\TaxisnetOAuthException;
use Sonole\GovGr\TaxisnetOAuth\Support\TaxisnetOAuthIdentity;
use Illuminate\Http\Client\ConnectionException;
use SimpleXMLElement;

class OAuthClient
{
    public function __construct(private readonly GovHttpClient $http)
    {
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $config = $this->config();

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->effectiveRedirectUri($redirectUri),
            'state' => $state,
        ]);

        return "{$config['base_url']}/oauth/authorize?{$query}";
    }

    /** @throws TaxisnetOAuthException */
    public function exchangeCodeForToken(string $code, string $redirectUri): string
    {
        $config = $this->config();

        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->effectiveRedirectUri($redirectUri),
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'scope' => config('govgr.taxisnet-oauth.scope', 'read'),
        ];

        $url = "{$config['base_url']}/oauth/token";

        $response = $this->http->request()->asForm()->post($url, $params);

        $accessToken = $response->successful() ? $response->json('access_token') : null;

        if (empty($accessToken)) {
            throw new TaxisnetOAuthException("Taxisnet OAuth token exchange failed with status {$response->status()}.");
        }

        return $accessToken;
    }

    /** @throws TaxisnetOAuthException|ConnectionException */
    public function userInfo(string $accessToken): TaxisnetOAuthIdentity
    {
        $config = $this->config();
        $url = "{$config['base_url']}/userinfo";

        $response = $this->http->request()->withToken($accessToken)->get($url, ['format' => 'xml']);

        if (! $response->successful()) {
            throw new TaxisnetOAuthException("Taxisnet OAuth userinfo request failed with status {$response->status()}.");
        }

        return TaxisnetOAuthIdentity::fromAttributes($this->parseUserInfoAttributes($response->body()));
    }

    public function logoutUrl(string $returnUrl): string
    {
        $config = $this->config();

        return "{$config['base_url']}/logout/{$config['client_id']}/?url=".rawurlencode($returnUrl);
    }

    /**
     * GSIS's userinfo response wraps an attribute-only <userinfo afm="..." .../> element
     * inside a root element. XmlHelper::toArray() round-trips through
     * json_encode(SimpleXMLElement), which drops XML attributes entirely, so we read
     * them straight off the SimpleXMLElement instead.
     *
     * @return array<string, string>
     *
     * @throws TaxisnetOAuthException
     */
    private function parseUserInfoAttributes(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $element = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $element instanceof SimpleXMLElement || ! isset($element->userinfo)) {
            throw new TaxisnetOAuthException('Taxisnet OAuth userinfo response was not the expected XML shape.');
        }

        $attributes = [];

        foreach ($element->userinfo->attributes() as $name => $value) {
            $attributes[$name] = (string) $value;
        }

        if (empty($attributes['taxid'])) {
            throw new TaxisnetOAuthException('Taxisnet OAuth userinfo response did not include a taxid attribute.');
        }

        return $attributes;
    }

    private function effectiveRedirectUri(string $redirectUri): string
    {
        return config('govgr.taxisnet-oauth.redirect_uri') ?: $redirectUri;
    }

    /** @return array{base_url: string, client_id: string, client_secret: string} */
    private function config(): array
    {
        $env = config('govgr.taxisnet-oauth.env', 'pilot');
        $config = config("govgr.taxisnet-oauth.{$env}", []);

        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['base_url'])) {
            $prefix = 'TAXISNET_OAUTH_'.strtoupper($env);

            throw new TaxisnetOAuthException(
                "Taxisnet OAuth credentials are not configured for the '{$env}' environment. Set {$prefix}_CLIENT_ID and {$prefix}_CLIENT_SECRET."
            );
        }

        return $config;
    }
}
