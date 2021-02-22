<?php
declare(strict_types=1);

namespace Pixelant\Interest\Router;

use Pixelant\Interest\Domain\Model\ResourceType;
use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface for Routers
 */
interface RouterInterface
{
    /**
     * Dispatch the request
     *
     * @param InterestRequestInterface $request
     * @return ResponseInterface|mixed
     */
    public function dispatch(InterestRequestInterface $request): ResponseInterface;

    /**
     * Add the given Route
     *
     * @param Route $route
     * @return RouterInterface
     */
    public function add(Route $route): RouterInterface;

    /**
     * Creates and registers a new Route with the given pattern and callback for the method GET
     *
     * @param string|ResourceType $pattern
     * @param callable            $callback
     * @return RouterInterface
     */
    public function routeGet($pattern, callable $callback): RouterInterface;

    /**
     * Creates and registers a new Route with the given pattern and callback for the method POST
     *
     * @param string|ResourceType $pattern
     * @param callable            $callback
     * @return RouterInterface
     */
    public function routePost($pattern, callable $callback): RouterInterface;

    /**
     * Creates and registers a new Route with the given pattern and callback for the method PUT
     *
     * @param string|ResourceType $pattern
     * @param callable            $callback
     * @return RouterInterface
     */
    public function routePut($pattern, callable $callback): RouterInterface;

    /**
     * Creates and registers a new Route with the given pattern and callback for the method DELETE
     *
     * @param string|ResourceType $pattern
     * @param callable            $callback
     * @return RouterInterface
     */
    public function routeDelete($pattern, callable $callback): RouterInterface;
}
