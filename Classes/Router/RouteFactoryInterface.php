<?php
declare(strict_types=1);

namespace Pixelant\Interest\Router;

use Pixelant\Interest\Domain\Model\ResourceType;

/**
 * Interface for Route factory methods
 */
interface RouteFactoryInterface
{
    /**
     * Creates a new Route with the given pattern and callback for the method GET
     *
     * @param string|ResourceType $pattern
     * @param callable $callback
     * @return Route
     */
    public static function get($pattern, callable $callback): Route;

    /**
     * Creates a new Route with the given pattern and callback for the method POST
     *
     * @param string|ResourceType $pattern
     * @param callable $callback
     * @return Route
     */
    public static function post($pattern, callable $callback): Route;

    /**
     * Creates a new Route with the given pattern and callback for the method PUT
     *
     * @param string|ResourceType $pattern
     * @param callable $callback
     * @return Route
     */
    public static function put($pattern, callable $callback): Route;

    /**
     * Creates a new Route with the given pattern and callback for the method DELETE
     *
     * @param string|ResourceType $pattern
     * @param callable $callback
     * @return Route
     */
    public static function delete($pattern, callable $callback): Route;
}
