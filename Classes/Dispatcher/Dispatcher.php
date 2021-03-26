<?php
declare(strict_types=1);

namespace Pixelant\Interest\Dispatcher;

use Pixelant\Interest\Handler\Exception\AbstractRequestHandlerException;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\RequestFactoryInterface;
use Pixelant\Interest\ResponseFactoryInterface;
use Pixelant\Interest\Router\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
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
     * Dispatcher constructor.
     * @param RequestFactoryInterface $requestFactory
     * @param ResponseFactoryInterface $responseFactory
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        RequestFactoryInterface $requestFactory,
        ResponseFactoryInterface $responseFactory,
        ObjectManagerInterface $objectManager
    )
    {
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->objectManager = $objectManager;
    }

    public function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->requestFactory->registerCurrentRequest($request);

        try {
            return $this->dispatch($this->requestFactory->getRequest());
        } catch (AbstractRequestHandlerException $exception) {
            return $this->responseFactory->createResponse(
                [
                    'status' => 'failure',
                    'message' => $exception->getMessage()
                ],
                $exception->getCode()
            );
        } catch (\Exception $exception) {
            $trace = [];

            if (GeneralUtility::makeInstance(ApplicationContext::class)->isDevelopment()) {
                $currentException = $exception;
                do {
                    $trace = array_merge(
                        $trace, [
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

            return $this->responseFactory->createResponse(
                [
                    'status' => 'failure',
                    'message' => 'An exception occurred: ' . $exception->getMessage(),
                    'trace' => $trace
                ],
                500
            );
        }
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function dispatch(InterestRequestInterface $request): ResponseInterface
    {
        if ($request->getResourceType()->__toString() === 'authentication'){
            return $this->callHandler($request);
        }

        $access = $this->objectManager->getAccessController()->getAccess($request);

        switch ($access){
            case true:
                return $this->callHandler($request);
            default:
                return $this->responseFactory->createErrorResponse('Unauthorized, please check if your token is valid', 401, $request);
        }
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
