<?php

declare(strict_types=1);

namespace Pixelant\Interest\Router;

use Pixelant\Interest\Authentication\HttpBackendUserAuthentication;
use Pixelant\Interest\DataHandling\Operation\Exception\AbstractException;
use Pixelant\Interest\Domain\Repository\TokenRepository;
use Pixelant\Interest\RequestHandler\AuthenticateRequestHandler;
use Pixelant\Interest\RequestHandler\CreateOrUpdateRequestHandler;
use Pixelant\Interest\RequestHandler\CreateRequestHandler;
use Pixelant\Interest\RequestHandler\DeleteRequestHandler;
use Pixelant\Interest\RequestHandler\Exception\AbstractRequestHandlerException;
use Pixelant\Interest\RequestHandler\Exception\InvalidArgumentException;
use Pixelant\Interest\RequestHandler\Exception\UnauthorizedAccessException;
use Pixelant\Interest\RequestHandler\ExceptionConverter\OperationToRequestHandlerExceptionConverter;
use Pixelant\Interest\RequestHandler\UpdateRequestHandler;
use Pixelant\Interest\Utility\CompatibilityUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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
     * @throws \Throwable
     */
    public static function route(ServerRequestInterface $request): ResponseInterface
    {
        self::initialize();

        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

        $entryPointParts = explode(
            '/',
            substr(
                $request->getRequestTarget(),
                strlen(
                    '/' . trim($extensionConfiguration->get('interest', 'entryPoint'), '/') . '/'
                )
            )
        );

        try {
            if ($entryPointParts[0] === 'authenticate') {
                return GeneralUtility::makeInstance(
                    AuthenticateRequestHandler::class,
                    $entryPointParts,
                    $request
                )->handle();
            }

            self::authenticateBearerToken($request);

            self::handleByMethod($request, $entryPointParts);
        } catch (AbstractRequestHandlerException $requestHandlerException) {
            return GeneralUtility::makeInstance(
                JsonResponse::class,
                [
                    'success' => false,
                    'message' => $requestHandlerException->getMessage(),
                ],
                $requestHandlerException->getCode()
            );
        } catch (\Throwable $throwable) {
            $trace = [];

            if (CompatibilityUtility::getApplicationContext()->isDevelopment()) {
                $trace = self::generateExceptionTrace($throwable);
            }

            return GeneralUtility::makeInstance(
                JsonResponse::class,
                [
                    'success' => false,
                    'message' => 'An exception occurred: ' . $throwable->getMessage(),
                    'trace' => $trace,
                ],
                500
            );
        }

        return GeneralUtility::makeInstance(
            JsonResponse::class,
            [
                'success' => false,
                'message' => 'Method not allowed.',
            ],
            405
        );
    }

    /**
     * Authenticates a token provided in the request.
     *
     * @param ServerRequestInterface $request
     * @throws UnauthorizedAccessException
     * @throws InvalidArgumentException
     */
    protected static function authenticateBearerToken(ServerRequestInterface $request): void
    {
        $authorizationHeader = $request->getHeader('authorization')[0]
            ?? $request->getHeader('redirect_http_authorization')[0]
            ?? '';

        [$scheme, $token] = GeneralUtility::trimExplode(' ', $authorizationHeader, true);

        if (strtolower($scheme) === 'bearer') {
            $backendUserId = GeneralUtility::makeInstance(TokenRepository::class)
                ->findBackendUserIdByToken($token);

            if ($backendUserId === 0) {
                throw new UnauthorizedAccessException(
                    'Invalid or expired bearer token.',
                    $request
                );
            }

            $GLOBALS['BE_USER']->authenticate($backendUserId);

            return;
        }

        throw new InvalidArgumentException(
            'Unknown authorization scheme "' . $scheme . '".',
            $request
        );
    }

    /**
     * Necessary initialization.
     */
    protected static function initialize()
    {
        Bootstrap::initializeBackendUser(HttpBackendUserAuthentication::class);
        ExtensionManagementUtility::loadExtTables();
        Bootstrap::initializeLanguageObject();
    }

    /**
     * @param $throwable
     * @param $trace
     * @return array
     */
    protected static function generateExceptionTrace(\Throwable $throwable): array
    {
        $currentThrowable = $throwable;
        do {
            $trace = array_merge(
                $trace,
                [
                    $currentThrowable->getMessage() => array_merge([
                        [
                            'file' => $currentThrowable->getFile(),
                            'line' => $currentThrowable->getLine(),
                        ],
                        $throwable->getTrace(),
                    ]),
                ]
            );

            $currentThrowable = $throwable->getPrevious();
        } while ($currentThrowable);
        return $trace;
    }

    /**
     * Handle a request depending on REST-compatible HTTP method.
     *
     * @param ServerRequestInterface $request
     * @param array $entryPointParts
     * @return ResponseInterface|void
     * @throws OperationToRequestHandlerExceptionConverter
     */
    protected static function handleByMethod(ServerRequestInterface $request, array $entryPointParts)
    {
        try {
            switch (strtoupper($request->getMethod())) {
                case 'POST':
                    return GeneralUtility::makeInstance(
                        CreateRequestHandler::class,
                        $entryPointParts,
                        $request
                    )->handle();
                case 'PUT':
                    return GeneralUtility::makeInstance(
                        UpdateRequestHandler::class,
                        $entryPointParts,
                        $request
                    )->handle();
                case 'PATCH':
                    return GeneralUtility::makeInstance(
                        CreateOrUpdateRequestHandler::class,
                        $entryPointParts,
                        $request
                    )->handle();
                case 'DELETE':
                    return GeneralUtility::makeInstance(
                        DeleteRequestHandler::class,
                        $entryPointParts,
                        $request
                    )->handle();
            }
        } catch (AbstractException $dataHandlingException) {
            throw OperationToRequestHandlerExceptionConverter::convert($dataHandlingException, $request);
        }
    }
}
