<?php
declare(strict_types=1);

namespace Pixelant\Interest\Router;

use Pixelant\Interest\Domain\Model\ResourceType;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\Router\RouteInterface;
use Psr\Http\Message\ResponseInterface;

class Route implements RouteInterface, RouteFactoryInterface
{
    /**
     * @var string
     */
    private $pattern;

    /**
     * @var array
     */
    private array $parameters = [];

    /**
     * @var string
     */
    private string $method;

    /**
     * @var callable
     */
    private $callback;

    /**
     * Route constructor
     *
     * @param string $pattern
     * @param string $method
     * @param callable $callback
     */
    public function __construct(string $pattern, string $method, callable $callback)
    {
        $this->pattern = $pattern;
        $this->method = strtoupper($method);
        $this->callback = $callback;
    }

    /**
     * @param string $pattern
     * @param callable $callback
     * @return Route
     */
    public static function get(string $pattern, callable $callback): Route
    {
        return new static($pattern, 'GET', $callback);
    }

    /**
     * @param string $pattern
     * @param callable $callback
     * @return Route
     */
    public static function post(string $pattern, callable $callback): Route
    {
        return new static($pattern, 'POST', $callback);
    }

    /**
     * @param string $pattern
     * @param callable $callback
     * @return Route
     */
    public static function put(string $pattern, callable $callback): Route
    {
        return new static($pattern, 'PUT', $callback);
    }

    /**
     * @param string $pattern
     * @param callable $callback
     * @return Route
     */
    public static function delete(string $pattern, callable $callback): Route
    {
        return new static($pattern, 'DELETE', $callback);
    }

    /**
     * Creates a new Route with the given pattern and callback for the method GET
     *
     * @param ResourceType|string $pattern
     * @param callable $callback
     * @return static
     */
    public static function routeWithPattern(string $pattern, callable $callback): self
    {
        return new static($pattern, 'GET', $callback);
    }

    /**
     * Creates a new Route with the given pattern, method and callback
     *
     * @param string $pattern
     * @param string $method
     * @param callable $callback
     * @return static
     */
    public static function routeWithPatternAndMethod(string $pattern, string $method, callable $callback): self
    {
        return new static($pattern, $method, $callback);
    }

    /**
     * Returns the normalized path pattern
     *
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Returns the request method for this route
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Returns the requested parameters
     *
     * @return string[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Process the route
     *
     * @param InterestRequestInterface $request
     * @param array                $parameters
     * @return ResponseInterface
     */
    public function process(InterestRequestInterface $request, ...$parameters): ResponseInterface
    {
        $callback = $this->callback;

        return $callback($request, ...$parameters);
    }
}

