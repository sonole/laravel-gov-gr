<?php

namespace Sonole\GovGr\Core\Exceptions;

/** The service answered, but holds nothing for what was asked (unknown or invalid AFM, unknown AMKA). */
class GovNotFoundException extends GovServiceException
{
}
