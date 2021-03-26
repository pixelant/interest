<?php

declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Configuration\ConfigurationProviderInterface;
use Pixelant\Interest\Controller\AccessControllerInterface;
use Pixelant\Interest\Handler\HandlerInterface;
use Pixelant\Interest\Http\InterestRequestInterface;

/**
 * Interface for the specialized Object Manager.
 */
interface ObjectManagerInterface
{
    /**
     * Return an instance of the given class.
     *
     * @param string $class The class name of the object to return an instance of
     * @param array $arguments
     * @return object The object instance
     */
    public function get(string $class, ...$arguments);

    /**
     * Returns the configuration provider.
     *
     * @return ConfigurationProviderInterface
     */
    public function getConfigurationProvider(): ConfigurationProviderInterface;

    /**
     * Returns the configuration provider.
     *
     * @return RequestFactoryInterface
     */
    public function getRequestFactory(): RequestFactoryInterface;

    /**
     * Returns the Response Factory.
     *
     * @return ResponseFactoryInterface
     */
    public function getResponseFactory(): ResponseFactoryInterface;

    /**
     * Returns the Access Controller.
     *
     * @param InterestRequestInterface|null $request Argument will be mandatory from version 5.0
     * @return AccessControllerInterface
     */
    public function getAccessController(InterestRequestInterface $request = null): AccessControllerInterface;

    /**
     * Returns the Handler which is responsible for handling the current request.
     *
     * @param InterestRequestInterface|null $request Argument will be mandatory from version 5.0
     * @return HandlerInterface
     */
    public function getHandler(InterestRequestInterface $request = null): HandlerInterface;
}
