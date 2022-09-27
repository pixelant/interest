<?php

declare(strict_types=1);

namespace Pixelant\Interest\Router\Event;

use Psr\Http\Message\ServerRequestInterface;

/**
 * An event that is called from HttpRequestRouter to determine what request method to use.
 */
class HttpRequestRouterHandleByEvent
{
    protected ServerRequestInterface $request;

    protected array $entryPointParts;

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
     * @param array $entryPointParts
     */
    public function setEntryPointParts(array $entryPointParts): void
    {
        $this->entryPointParts = $entryPointParts;
    }

    /**
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @param ServerRequestInterface $request
     */
    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }
}
