<?php

namespace Pixelant\Interest\Middleware;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\Middleware\Event\HttpResponseEvent;
use Pixelant\Interest\Router\HttpRequestRouter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RequestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var ExtensionConfiguration $extensionConfiguration */
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
            $executionStart = round(microtime(true) * 1000);

            $response = HttpRequestRouter::route($request);

            $response = GeneralUtility::makeInstance(EventDispatcher::class)->dispatch(
                new HttpResponseEvent($response)
            )->getResponse();

            $executionTime = (int)(round(microtime(true) * 1000) - $executionStart);

            $this->logRequest($request, $response, $executionTime);

            return $response;
        }

        return $handler->handle($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $executionTime
     */
    protected function logRequest(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $executionTime
    ): void {
        /** @var ConfigurationProvider $configuration */
        $configuration = GeneralUtility::makeInstance(ConfigurationProvider::class);
        if ($configuration->isLoggingEnabledForExecutionTime($executionTime)) {
            if ($configuration->isHeaderLoggingEnabled()) {
                $response = $response->withAddedHeader(
                    'x-typo3-interest-ms',
                    (string)$executionTime
                );
            }

            if ($configuration->isDatabaseLoggingEnabled()) {
                /** @var QueryBuilder $queryBuilder */
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tx_interest_log');

                $queryBuilder
                    ->insert('tx_interest_log')
                    ->values([
                        'timestamp' => time(),
                        'execution_time' => $executionTime,
                        'status_code' => $response->getStatusCode(),
                        'method' => $request->getMethod(),
                        'uri' => (string)$request->getUri(),
                        'request_headers' => substr(json_encode($request->getHeaders()), 0, 65535),
                        'request_body' => substr((string)$request->getBody(), 0, 16777215),
                        'response_headers' => substr(json_encode($response->getHeaders()), 0, 65535),
                        'response_body' => substr((string)$response->getBody(), 0, 16777215),
                    ])
                    ->executeStatement();
            }
        }
    }
}
