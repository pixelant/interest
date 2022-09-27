<?php

declare(strict_types=1);

namespace Pixelant\Interest\Router\Event;

interface HttpRequestRouterHandleByEventHandlerInterface
{
    /**
     * Handle a HttpRequestRouterHandleByEvent.
     *
     * @param HttpRequestRouterHandleByEvent $event
     */
    public function __invoke(HttpRequestRouterHandleByEvent $event): void;
}
