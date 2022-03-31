<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler\Exception;

class InvalidArgumentException extends AbstractRequestHandlerException
{
    protected const RESPONSE_CODE = 400;
}
