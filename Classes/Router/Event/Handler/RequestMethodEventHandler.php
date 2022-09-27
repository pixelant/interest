<?php

declare(strict_types=1);

namespace Pixelant\Interest\Router\Event\Handler;

use Pixelant\Interest\Router\Event\HttpRequestRouterMethodEvent;
use Pixelant\Interest\Router\Event\HttpRequestRouterMethodEventHandlerInterface;

class RequestMethodEventHandler implements HttpRequestRouterMethodEventHandlerInterface
{
    /**
     * @param HttpRequestRouterMethodEvent $event
     */
    public function __invoke(HttpRequestRouterMethodEvent $event): void
    {
        $event->setMethod($event->getRequest()->getMethod());
    }
}
