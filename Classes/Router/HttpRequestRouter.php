<?php
declare(strict_types=1);

namespace Pixelant\Interest\Router;

use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\Domain\Repository\TokenRepository;
use Pixelant\Interest\RequestHandler\AuthenticateRequestHandler;
use Pixelant\Interest\RequestHandler\CreateRequestHandler;
use Pixelant\Interest\RequestHandler\DeleteRequestHandler;
use Pixelant\Interest\RequestHandler\UpdateRequestHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Routes requests to the correct handler and converts exceptions to responses.
 */
class HttpRequestRouter
{
    /**
     * Route the request to correct handler.
     *
     * @return ResponseInterface
     */
    public static function route(ServerRequestInterface $request): ResponseInterface
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

        $entryPointParts = explode(
            '/',
            substr(
                $request->getRequestTarget(),
                strpos(
                    $request->getRequestTarget(),
                    '/' . trim($extensionConfiguration->get('interest', 'entryPoint'), '/') . '/'
                ) + 1
            )
        );

        if ($entryPointParts[0] === 'authenticate') {
            return GeneralUtility::makeInstance(AuthenticateRequestHandler::class, $entryPointParts)->handle();
        }

        switch (strtoupper($request->getMethod())) {
            case 'POST':
                return GeneralUtility::makeInstance(CreateRequestHandler::class, $entryPointParts)->handle();
            case 'PUT':
                return GeneralUtility::makeInstance(UpdateRequestHandler::class, $entryPointParts)->handle();
            case 'PATCH':
                try {
                    return GeneralUtility::makeInstance(UpdateRequestHandler::class, $entryPointParts)->handle();
                } catch (NotFoundException $exception) {
                    return GeneralUtility::makeInstance(CreateRequestHandler::class, $entryPointParts)->handle();
                }
            case 'DELETE':
                return GeneralUtility::makeInstance(DeleteRequestHandler::class, $entryPointParts)->handle();
        }

        return GeneralUtility::makeInstance(
            JsonResponse::class,
            [
                'success' => false,
                'message' => 'Method not allowed.'
            ],
            405
        );
    }

    /**
     * Authenticates a token provided in the request.
     *
     * @param ServerRequestInterface $request
     */
    protected static function authenticateBearerToken(ServerRequestInterface $request): void
    {
        $authorizationHeader = $request->getHeader('HTTP_AUTHORIZATION')[0]
            ?? $request->getHeader('REDIRECT_HTTP_AUTHORIZATION')[0]
            ?? '';

        [$scheme, $token] = GeneralUtility::trimExplode(' ', $authorizationHeader, true);

        if (strtolower($scheme) === 'bearer') {
            $backendUserId = GeneralUtility::makeInstance(TokenRepository::class)
                ->findBackendUserIdByToken($token);


        }


    }
}
