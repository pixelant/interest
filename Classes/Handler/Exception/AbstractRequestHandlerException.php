<?php

declare(strict_types=1);

namespace Pixelant\Interest\Handler\Exception;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Abstract class for handler exceptions.
 */
abstract class AbstractRequestHandlerException extends GuzzleRequestException implements RequestExceptionInterface
{
    protected const RESPONSE_CODE = 400;

    /**
     * @param string $message
     * @param RequestInterface $request
     * @param GuzzleRequestException $previous
     */
    public function __construct(string $message, RequestInterface $request)
    {
        parent::__construct($message, $request, null);
        $this->code = static::RESPONSE_CODE;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return parent::getRequest();
    }
}
