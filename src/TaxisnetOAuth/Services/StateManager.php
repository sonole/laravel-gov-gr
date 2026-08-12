<?php

namespace Sonole\GovGr\TaxisnetOAuth\Services;

use Illuminate\Support\Str;

/** Session-backed CSRF `state` handling for the OAuth2 authorization-code flow. */
class StateManager
{
    private const SESSION_KEY = 'taxisnet_oauth_state';

    public function generate(): string
    {
        return Str::random(40);
    }

    public function store(string $state): void
    {
        session([self::SESSION_KEY => $state]);
    }

    /** Compares $state against this session's stored value with hash_equals(). */
    public function verify(string $state): bool
    {
        $expected = session(self::SESSION_KEY);

        return is_string($expected) && $state !== '' && hash_equals($expected, $state);
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
