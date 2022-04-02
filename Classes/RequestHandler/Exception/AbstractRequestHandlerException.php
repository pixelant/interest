<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler\Exception;

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Psr\Http\Message\RequestInterface;

/**
 * Abstract class for handler exceptions.
 */
abstract class AbstractRequestHandlerException extends GuzzleRequestException
{
    protected const RESPONSE_CODE = 400;

    /**
     * @param string $message
     * @param RequestInterface $request
     */
    public function __construct(string $message, RequestInterface $request)
    {
        parent::__construct($message, $request, null);
        $this->code = static::RESPONSE_CODE;
    }

    /**
     * @return RequestInterface
     *
     * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
     */
    public function getRequest(): RequestInterface
    {
        return parent::getRequest();
    }
}
