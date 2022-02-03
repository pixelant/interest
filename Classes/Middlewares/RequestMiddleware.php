<?php

namespace Pixelant\Interest\Middlewares;

use Pixelant\Interest\BootstrapDispatcher;
use Pixelant\Interest\ObjectManager;
use Pixelant\Interest\Router\HttpRequestRouter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RequestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

        if (
            strpos(
                $request->getRequestTarget(),
                '/' . trim($extensionConfiguration->get('interest', 'entryPoint'), '/') . '/'
            ) === 0
        ) {
            HttpRequestRouter::route($request);
        }

        return $handler->handle($request);
    }
}
