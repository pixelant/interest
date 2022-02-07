<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler\Exception;

/**
 * Exception issued in cases where HTTP Authentication fails. Should not be used for TYPO3 backend user access
 * restriction errors.
 */
class UnauthorizedAccessException extends AbstractRequestHandlerException
{
    protected const RESPONSE_CODE = 401;
}
