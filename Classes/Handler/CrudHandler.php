<?php
declare(strict_types=1);

namespace Pixelant\Interest\Handler;

use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;

class CrudHandler implements HandlerInterface
{
    /**
     * @param InterestRequestInterface $request
     */
    public function listAll(InterestRequestInterface $request)
    {
        var_dump($request->getResourceType());
        die();
    }

    /**
     * @param RouterInterface $router
     * @param InterestRequestInterface $request
     */
    public function configureRoutes(RouterInterface $router, InterestRequestInterface $request)
    {
        $resourceType = $request->getResourceType()->__toString();
        $router->add(Route::get($resourceType, [$this, 'listAll']));
    }
}
