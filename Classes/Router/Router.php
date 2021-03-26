<?php

declare(strict_types=1);

namespace Pixelant\Interest\Router;

use Pixelant\Interest\Domain\Model\ResourceType;
use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;

class Router implements RouterInterface
{
    /**
     * @var array[]
     */
    protected array $registeredRoutes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
    ];

    /**
     * Dispatch the request.
     *
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws RouteNotFoundException
     */
    public function dispatch(InterestRequestInterface $request): ResponseInterface
    {
        $route = $this->getMatchingRoute($request);
        if (!$route) {
            throw new RouteNotFoundException('No matching configured routes for your request');
        }

        return $route->process($request);
    }

    /**
     * @param InterestRequestInterface $request
     * @return Route
     */
    private function getMatchingRoute(InterestRequestInterface $request): Route
    {
        $matchingRoutes = $this->getMatchingRoutes($request);

        return reset($matchingRoutes);
    }

    /**
     * @param InterestRequestInterface $request
     * @return Route[]
     */
    public function getMatchingRoutes(InterestRequestInterface $request): array
    {
        $registeredRoutes = $this->getRoutesForMethod($request);
        if (empty($registeredRoutes)) {
            return [];
        }

        $path = trim($request->getPath(), '/');
        $matchingRoutes = [];
        foreach ($registeredRoutes as $pattern => $route) {
            if ($pattern === $path) {
                $matchingRoutes[] = $route;
            }
        }

        return $matchingRoutes;
    }

    /**
     * @param InterestRequestInterface $request
     * @return array
     */
    private function getRoutesForMethod(InterestRequestInterface $request): array
    {
        return $this->registeredRoutes[$request->getMethod()] ?? [];
    }

    /**
     * @param Route $route
     * @return RouterInterface
     */
    public function add(Route $route): RouterInterface
    {
        $method = $route->getMethod();
        if (!isset($this->registeredRoutes[$method])) {
            $this->registeredRoutes[$method] = [];
        }

        $this->registeredRoutes[$method][$route->getPattern()] = $route;

        return $this;
    }

    /**
     * @param ResourceType|string $pattern
     * @param callable $callback
     * @return RouterInterface
     */
    public function routeGet($pattern, callable $callback): RouterInterface
    {
        $this->add(Route::get($pattern, $callback));

        return $this;
    }

    /**
     * @param ResourceType|string $pattern
     * @param callable $callback
     * @return RouterInterface
     */
    public function routePost($pattern, callable $callback): RouterInterface
    {
        $this->add(Route::post($pattern, $callback));

        return $this;
    }

    /**
     * @param ResourceType|string $pattern
     * @param callable $callback
     * @return RouterInterface
     */
    public function routePut($pattern, callable $callback): RouterInterface
    {
        $this->add(Route::put($pattern, $callback));

        return $this;
    }

    /**
     * @param ResourceType|string $pattern
     * @param callable $callback
     * @return RouterInterface
     */
    public function routeDelete($pattern, callable $callback): RouterInterface
    {
        $this->add(Route::delete($pattern, $callback));

        return $this;
    }

    /**
     * @param ResourceType|string $pattern
     * @param callable $callback
     * @return RouterInterface
     */
    public function routePatch($pattern, callable $callback): RouterInterface
    {
        $this->add(Route::patch($pattern, $callback));

        return $this;
    }
}
