<?php

declare(strict_types=1);

use Pixelant\Interest\Handler\Exception\AbstractRequestHandlerException;

class MissingArgumentsException extends AbstractRequestHandlerException
{
    protected const RESPONSE_CODE = 404;
}
