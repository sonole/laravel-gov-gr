<?php

namespace Sonole\GovGr\Tests;

/**
 * Minimal stand-in for an authenticated user model. DefaultIdentityResolver
 * only reads ->email and ->getKey(), so a real Eloquent model isn't needed.
 */
class FakeAuthenticatable
{
    public function __construct(
        private readonly int $id,
        public readonly string $email,
    ) {
    }

    public function getKey(): int
    {
        return $this->id;
    }
}