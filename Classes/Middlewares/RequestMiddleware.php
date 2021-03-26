<?php

namespace Pixelant\Interest\Middlewares;

use Pixelant\Interest\BootstrapDispatcher;
use Pixelant\Interest\ObjectManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RequestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $bootstrapDispatcher = $objectManager->get(BootstrapDispatcher::class);

        if (preg_match('/rest/', explode('/', $request->getRequestTarget())[1])) {
            return $bootstrapDispatcher->processRequest($request);
        }

        return $handler->handle($request);
    }
}
