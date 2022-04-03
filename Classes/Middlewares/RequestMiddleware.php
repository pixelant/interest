<?php

namespace Pixelant\Interest\Middlewares;

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

        $entryPoint
            = getenv('APP_INTEREST_ENTRY_POINT') !== false
            ? getenv('APP_INTEREST_ENTRY_POINT')
            : $extensionConfiguration->get('interest', 'entryPoint');

        if (
            strpos(
                $request->getRequestTarget(),
                '/' . trim($entryPoint, '/') . '/'
            ) === 0
        ) {
            return HttpRequestRouter::route($request);
        }

        return $handler->handle($request);
    }
}
