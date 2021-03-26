<?php

declare(strict_types=1);

namespace Pixelant\Interest\Handler;

use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\Router\RouterInterface;

interface HandlerInterface
{
    /**
     * Let the handler configure the routes.
     *
     * @param RouterInterface $router
     * @param InterestRequestInterface $request
     */
    public function configureRoutes(RouterInterface $router, InterestRequestInterface $request);
}
