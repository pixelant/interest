<?php

declare(strict_types=1);

namespace Pixelant\Interest\Handler\Exception;

/**
 * Exception issued for backend user access restriction errors.
 */
class AccessRestrictedException extends AbstractRequestHandlerException
{
    protected const RESPONSE_CODE = 403;
}
