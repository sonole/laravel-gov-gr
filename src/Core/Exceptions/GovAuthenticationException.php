<?php

namespace Sonole\GovGr\Core\Exceptions;

/** WS-Security failure: wrong credentials, missing Security header, or empty config. Used by any module. */
class GovAuthenticationException extends GovServiceException
{
}