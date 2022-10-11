<?php

declare(strict_types=1);

namespace Pixelant\Interest\Middleware\Event;

use Psr\Http\Message\ResponseInterface;

/**
 * An event created at the very end of the interest request, right before control is passed back to TYPO3.
 */
class HttpResponseEvent
{
    protected ResponseInterface $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * @param ResponseInterface $response
     */
    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
    }
}
