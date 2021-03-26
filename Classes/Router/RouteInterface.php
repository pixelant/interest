<?php

declare(strict_types=1);

namespace Pixelant\Interest\Router;

use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface RouteInterface
{
    /**
     * Process the route.
     *
     * @param InterestRequestInterface $request
     * @param array $parameters
     * @return ResponseInterface
     */
    public function process(InterestRequestInterface $request, ...$parameters): ResponseInterface;

    /**
     * Returns the normalized path pattern.
     *
     * @return string
     */
    public function getPattern(): string;

    /**
     * Returns the request method for this route.
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Returns the requested parameters.
     *
     * @return string[]
     */
    public function getParameters(): array;
}
