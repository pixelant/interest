<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler\Exception;

class MissingArgumentException extends AbstractRequestHandlerException
{
    protected const RESPONSE_CODE = 404;
}
