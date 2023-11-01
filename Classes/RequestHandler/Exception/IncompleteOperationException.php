<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler\Exception;

/**
 * @see \Pixelant\Interest\DataHandling\Operation\Exception\IncompleteOperationException
 */
class IncompleteOperationException extends AbstractRequestHandlerException
{
    protected const RESPONSE_CODE = 400;
}
