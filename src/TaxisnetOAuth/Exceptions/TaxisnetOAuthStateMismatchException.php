<?php

namespace Sonole\GovGr\TaxisnetOAuth\Exceptions;

/** Thrown by handleCallback() when the request's `state` doesn't match this session's stored state. */
class TaxisnetOAuthStateMismatchException extends TaxisnetOAuthException
{
}
