<?php

declare(strict_types=1);


namespace Pixelant\Interest\Handler\Exception;

/**
 * Exception to throw if request data conflicts with existing data.
 */
class ConflictException extends AbstractRequestHandlerException
{
    protected const RESPONSE_CODE = 409;
}
