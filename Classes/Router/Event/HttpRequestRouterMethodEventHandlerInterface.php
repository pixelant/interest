<?php

declare(strict_types=1);

namespace Pixelant\Interest\Router\Event;

interface HttpRequestRouterMethodEventHandlerInterface
{
    /**
     * Handle a HttpRequestRouterMethodEvent.
     *
     * @param HttpRequestRouterMethodEvent $event
     */
    public function __invoke(HttpRequestRouterMethodEvent $event): void;
}
