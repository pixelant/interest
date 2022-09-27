<?php

declare(strict_types=1);

namespace Pixelant\Interest\Router\Event;

use Psr\Http\Message\ServerRequestInterface;

/**
 * An event that is called from HttpRequestRouter to determine what request method to use.
 */
class HttpRequestRouterMethodEvent
{
    protected ServerRequestInterface $request;

    protected array $entryPointParts;

    protected string $method;

    /**
     * @param ServerRequestInterface $request
     * @param array $entryPointParts
     */
    public function __construct(ServerRequestInterface $request, array $entryPointParts)
    {
        $this->request = $request;
        $this->entryPointParts = $entryPointParts;
    }

    /**
     * @return string[]
     */
    public function getEntryPointParts(): array
    {
        return $this->entryPointParts;
    }

    /**
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }
}
