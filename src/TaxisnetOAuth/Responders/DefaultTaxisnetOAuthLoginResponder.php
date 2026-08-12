<?php

namespace Sonole\GovGr\TaxisnetOAuth\Responders;

use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLoginResponder;
use Sonole\GovGr\TaxisnetOAuth\Support\TaxisnetOAuthIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Default behavior: returns the verified identity as JSON. Does not touch your User
 * model or session. See the package README for an example of a real responder.
 */
class DefaultTaxisnetOAuthLoginResponder implements TaxisnetOAuthLoginResponder
{
    public function respond(TaxisnetOAuthIdentity $identity, Request $request): JsonResponse
    {
        return response()->json($identity->toArray());
    }
}
