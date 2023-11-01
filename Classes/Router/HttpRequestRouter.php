<?php

declare(strict_types=1);

namespace Pixelant\Interest\Router;

use Pixelant\Interest\Authentication\HttpBackendUserAuthenticationForTypo3v11;
use Pixelant\Interest\Authentication\HttpBackendUserAuthenticationForTypo3v12;
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
use Pixelant\Interest\Router\Event\HttpRequestRouterHandleByEvent;
use Pixelant\Interest\Utility\CompatibilityUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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
        self::initialize($request);

        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

        $entryPoint = substr(
            $request->getRequestTarget(),
            strlen(
                '/' . trim($extensionConfiguration->get('interest', 'entryPoint'), '/') . '/'
            )
        );

        if ($entryPoint === '') {
            $entryPointParts = [];
        } else {
            $entryPointParts = explode(
                '/',
                $entryPoint
            );
        }

        try {
            if (($entryPointParts[0] ?? null) === 'authenticate') {
                return GeneralUtility::makeInstance(
                    AuthenticateRequestHandler::class,
                    $entryPointParts,
                    $request
                )->handle();
            }

            self::authenticateBearerToken($request);

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

        if (is_string($scheme) && strtolower($scheme) === 'bearer') {
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
    protected static function initialize(ServerRequestInterface $request)
    {
        if (CompatibilityUtility::typo3VersionIsLessThan('12.0')) {
            require_once GeneralUtility::getFileAbsFileName(
                'EXT:interest/DynamicCompatibility/Authentication/HttpBackendUserAuthenticationForTypo3v11.php'
            );

            // @phpstan-ignore-next-line
            Bootstrap::initializeBackendUser(HttpBackendUserAuthenticationForTypo3v11::class, $request);
        } else {
            require_once GeneralUtility::getFileAbsFileName(
                'EXT:interest/DynamicCompatibility/Authentication/HttpBackendUserAuthenticationForTypo3v12.php'
            );

            // @phpstan-ignore-next-line
            Bootstrap::initializeBackendUser(HttpBackendUserAuthenticationForTypo3v12::class, $request);
        }

        self::bootFrontendController($request);

        ExtensionManagementUtility::loadExtTables();
        Bootstrap::initializeLanguageObject();
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
     * @return ResponseInterface
     * phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
     */
    protected static function handleByMethod(ServerRequestInterface $request, array $entryPointParts): ResponseInterface
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

    /**
     * Booting up TSFE to make TSFE->sys_page available for ResourceFactory.
     *
     * @param ServerRequestInterface $request
     */
    protected static function bootFrontendController(ServerRequestInterface $request): void
    {
        /** @var Site $site */
        $site = $request->getAttribute('site', null);
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $controller = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            GeneralUtility::makeInstance(Context::class),
            $site,
            $site->getDefaultLanguage(),
            new PageArguments($site->getRootPageId(), '0', []),
            $frontendUser
        );
        if (!isset($GLOBALS['TSFE']) || !$GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            $GLOBALS['TSFE'] = $controller;
        }
        if (!$GLOBALS['TSFE']->sys_page instanceof PageRepository) {
            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        }
    }
}
