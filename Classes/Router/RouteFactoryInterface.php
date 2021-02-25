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
     * @param string $pattern
     * @param callable $callback
     * @return Route
     */
    public static function get(string $pattern, callable $callback): Route;

    /**
     * Creates a new Route with the given pattern and callback for the method POST
     *
     * @param string $pattern
     * @param callable $callback
     * @return Route
     */
    public static function post(string $pattern, callable $callback): Route;

    /**
     * Creates a new Route with the given pattern and callback for the method PUT
     *
     * @param string $pattern
     * @param callable $callback
     * @return Route
     */
    public static function put(string $pattern, callable $callback): Route;

    /**
     * Creates a new Route with the given pattern and callback for the method DELETE
     *
     * @param string $pattern
     * @param callable $callback
     * @return Route
     */
    public static function delete(string $pattern, callable $callback): Route;
}
