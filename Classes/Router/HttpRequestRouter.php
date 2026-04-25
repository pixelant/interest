<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Router;

use FriendsOfTYPO3\Interest\Authentication\HttpBackendUserAuthentication;
use FriendsOfTYPO3\Interest\DataHandling\Operation\Exception\AbstractException;
use FriendsOfTYPO3\Interest\RequestHandler\AuthenticateRequestHandler;
use FriendsOfTYPO3\Interest\RequestHandler\CreateOrUpdateRequestHandler;
use FriendsOfTYPO3\Interest\RequestHandler\CreateRequestHandler;
use FriendsOfTYPO3\Interest\RequestHandler\DeleteRequestHandler;
use FriendsOfTYPO3\Interest\RequestHandler\Exception\AbstractRequestHandlerException;
use FriendsOfTYPO3\Interest\RequestHandler\ExceptionConverter\OperationToRequestHandlerExceptionConverter;
use FriendsOfTYPO3\Interest\RequestHandler\UpdateRequestHandler;
use FriendsOfTYPO3\Interest\Router\Event\HttpRequestRouterHandleByEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
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
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

        $entryPoint = substr(
            $request->getRequestTarget(),
            strlen(
                '/' . trim($extensionConfiguration->get('interest', 'entryPoint'), '/') . '/'
            )
        );

        $entryPointParts = $entryPoint === '' ? [] : explode(
            '/',
            $entryPoint
        );

        try {
            self::initialize($request);

            if (($entryPointParts[0] ?? null) === 'authenticate') {
                return GeneralUtility::makeInstance(
                    AuthenticateRequestHandler::class,
                    $entryPointParts,
                    $request
                )->handle();
            }

            return self::handleByMethod($request, $entryPointParts);
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

            if (Environment::getContext()->isDevelopment()) {
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
    }

    /**
     * Necessary initialization.
     */
    protected static function initialize(ServerRequestInterface $request)
    {
        Bootstrap::initializeBackendUser(HttpBackendUserAuthentication::class, $request);

        Bootstrap::loadExtTables();

        $GLOBALS['LANG'] ??= GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    /**
     * @param \Throwable $throwable
     * @return array
     */
    protected static function generateExceptionTrace(\Throwable $throwable): array
    {
        $trace = [];

        $currentThrowable = $throwable;

        do {
            $trace = array_merge(
                $trace,
                [
                    $currentThrowable->getMessage() => [[
                        'file' => $currentThrowable->getFile(),
                        'line' => $currentThrowable->getLine(),
                    ], $throwable->getTrace()],
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
     * @return ResponseInterface
     * @internal
     * phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
     */
    public static function handleByMethod(ServerRequestInterface $request, array $entryPointParts): ResponseInterface
    {
        $event = GeneralUtility::makeInstance(EventDispatcher::class)->dispatch(
            new HttpRequestRouterHandleByEvent($request, $entryPointParts)
        );

        try {
            switch (strtoupper($event->getRequest()->getMethod())) {
                case 'POST':
                    return GeneralUtility::makeInstance(
                        CreateRequestHandler::class,
                        $event->getEntryPointParts(),
                        $event->getRequest()
                    )->handle();
                case 'PUT':
                    return GeneralUtility::makeInstance(
                        UpdateRequestHandler::class,
                        $event->getEntryPointParts(),
                        $event->getRequest()
                    )->handle();
                case 'PATCH':
                    return GeneralUtility::makeInstance(
                        CreateOrUpdateRequestHandler::class,
                        $event->getEntryPointParts(),
                        $event->getRequest()
                    )->handle();
                case 'DELETE':
                    return GeneralUtility::makeInstance(
                        DeleteRequestHandler::class,
                        $event->getEntryPointParts(),
                        $event->getRequest()
                    )->handle();
            }
        } catch (AbstractException $dataHandlingException) {
            throw OperationToRequestHandlerExceptionConverter::convert($dataHandlingException, $request);
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
}
