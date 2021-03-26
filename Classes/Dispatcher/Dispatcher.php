<?php

declare(strict_types=1);

namespace Pixelant\Interest\Dispatcher;

use Pixelant\Interest\Configuration\ConfigurationProviderInterface;
use Pixelant\Interest\Handler\Exception\AbstractRequestHandlerException;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\RequestFactoryInterface;
use Pixelant\Interest\ResponseFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Dispatcher implements DispatcherInterface
{
    /**
     * @var RequestFactoryInterface
     */
    protected RequestFactoryInterface $requestFactory;

    /**
     * @var ResponseFactoryInterface
     */
    protected ResponseFactoryInterface $responseFactory;

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var ConfigurationProviderInterface
     */
    protected ConfigurationProviderInterface $configuration;

    /**
     * Dispatcher constructor.
     * @param RequestFactoryInterface $requestFactory
     * @param ResponseFactoryInterface $responseFactory
     * @param ObjectManagerInterface $objectManager
     * @param ConfigurationProviderInterface $configurationProvider
     */
    public function __construct(
        RequestFactoryInterface $requestFactory,
        ResponseFactoryInterface $responseFactory,
        ObjectManagerInterface $objectManager,
        ConfigurationProviderInterface $configurationProvider
    ) {
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->objectManager = $objectManager;
        $this->configuration = $configurationProvider;
    }

    /**
     * Main entry point for incoming request processing.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->requestFactory->registerCurrentRequest($request);

        $executionStart = round(microtime(true) * 1000);

        try {
            $response = $this->dispatch($this->requestFactory->getRequest());
        } catch (AbstractRequestHandlerException $exception) {
            $response = $this->responseFactory->createResponse(
                [
                    'status' => 'failure',
                    'message' => $exception->getMessage(),
                ],
                $exception->getCode()
            );
        } catch (\Exception $exception) {
            $trace = [];

            if (GeneralUtility::makeInstance(ApplicationContext::class)->isDevelopment()) {
                $currentException = $exception;
                do {
                    $trace = array_merge(
                        $trace,
                        [
                            $currentException->getMessage() => array_merge([
                                [
                                    'file' => $currentException->getFile(),
                                    'line' => $currentException->getLine(),
                                ],
                                $exception->getTrace(),
                            ]),
                        ]
                    );
                } while ($currentException = $exception->getPrevious());
            }

            $response = $this->responseFactory->createResponse(
                [
                    'status' => 'failure',
                    'message' => 'An exception occurred: ' . $exception->getMessage(),
                    'trace' => $trace,
                ],
                500
            );
        }

        $executionTime = (int)(round(microtime(true) * 1000) - $executionStart);

        $response = $this->logRequest($request, $response, $executionTime);

        return $response;
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function dispatch(InterestRequestInterface $request): ResponseInterface
    {
        if ($request->getResourceType()->__toString() === 'authentication') {
            return $this->callHandler($request);
        }

        $access = $this->objectManager->getAccessController()->getAccess($request);

        // @codingStandardsIgnoreStart
        switch ($access) {
            case true:
                return $this->callHandler($request);
            default:
                return $this->responseFactory->createErrorResponse('Unauthorized, please check if your token is valid', 401, $request);
        }
        // @codingStandardsIgnoreEnd
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param int $executionTime
     */
    protected function logRequest(RequestInterface $request, ResponseInterface $response, int $executionTime): ResponseInterface
    {
        if ($this->configuration->isLoggingEnabledForExecutionTime($executionTime)) {
            if ($this->configuration->isHeaderLoggingEnabled()) {
                $response = $response->withAddedHeader(
                    'x-typo3-interest-ms',
                    (string)$executionTime
                );
            }

            if ($this->configuration->isDatabaseLoggingEnabled()) {
                /** @var QueryBuilder $queryBuilder */
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tx_interest_log');

                $queryBuilder
                    ->insert('tx_interest_log')
                    ->values([
                        'timestamp' => time(),
                        'execution_time' => $executionTime,
                        'request_headers' => substr(json_encode($request->getHeaders()), 0, 65535),
                        'request_body' => substr((string)$request->getBody(), 0, 16777215),
                        'response_headers' => substr(json_encode($response->getHeaders()), 0, 65535),
                        'response_body' => substr((string)$response->getBody(), 0, 16777215),
                    ])
                    ->execute();
            }
        }

        return $response;
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function callHandler(InterestRequestInterface $request): ResponseInterface
    {
        $router = $this->objectManager->getRouter();
        $this->objectManager->getHandler($request)->configureRoutes($router, $request);

        return $router->dispatch($request);
    }
}
