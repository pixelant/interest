<?php

declare(strict_types=1);

namespace Pixelant\Interest\Handler\Exception;

class MissingArgumentsException extends AbstractRequestHandlerException
{
    protected const RESPONSE_CODE = 404;
}
