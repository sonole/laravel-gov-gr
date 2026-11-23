<?php

namespace Sonole\GovGr\Core\Exceptions;

/**
 * The service could not give an answer: bad or missing credentials, a transport failure (timeout, connection
 * refused) or a 5xx. The reverse of GovNotFoundException, where the service answered and had nothing.
 * Safe to retry later; GovAuthenticationException is a subtype, and needs a configuration fix instead.
 */
class GovUnavailableException extends GovServiceException
{
}
