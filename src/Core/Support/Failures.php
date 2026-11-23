<?php

namespace Sonole\GovGr\Core\Support;

use Illuminate\Http\Client\HttpClientException;
use Sonole\GovGr\Core\Exceptions\GovApiException;
use Sonole\GovGr\Core\Exceptions\GovNotFoundException;
use Sonole\GovGr\Core\Exceptions\GovUnavailableException;
use Throwable;

/** Sorts what a service call throws into the three outcomes callers care about: found, not found, unavailable. */
final class Failures
{
    /**
     * Transport errors and any HTTP error status (a 403 from an IP that is not whitelisted, a 5xx) become
     * GovUnavailableException; a credentials error already is one. A business rejection ("no such AFM") comes
     * back as HTTP 200 with an errorRecord, so it is returned unchanged, like an InvalidArgumentException.
     */
    public static function sort(Throwable $e): Throwable
    {
        return match (true) {
            $e instanceof GovUnavailableException => $e,
            $e instanceof GovApiException && ($e->httpStatusCode() ?? 0) >= 400 => GovUnavailableException::wrapping($e),
            $e instanceof HttpClientException => new GovUnavailableException($e->getMessage(), 0, $e),
            default => $e,
        };
    }

    /** sort(), and a remaining service rejection ("no such person") becomes GovNotFoundException. */
    public static function sortRejection(Throwable $e): Throwable
    {
        $e = self::sort($e);

        return $e instanceof GovApiException && ! $e instanceof GovUnavailableException && ! $e instanceof GovNotFoundException
            ? GovNotFoundException::wrapping($e)
            : $e;
    }
}
