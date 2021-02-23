<?php
declare(strict_types=1);

namespace Pixelant\Interest\Dispatcher;

use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\RequestFactoryInterface;
use Pixelant\Interest\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
     * Dispatcher constructor.
     * @param RequestFactoryInterface $requestFactory
     * @param ResponseFactoryInterface $responseFactory
     */
    public function __construct(
        RequestFactoryInterface $requestFactory,
        ResponseFactoryInterface $responseFactory
    )
    {
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
    }

    public function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->requestFactory->registerCurrentRequest($request);

        return $this->dispatch($this->requestFactory->getRequest());
    }

    public function dispatch(InterestRequestInterface $request): ResponseInterface
    {
        //Here will be checks for authorization.

        $this->callHandler($request);
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    private function callHandler(InterestRequestInterface $request): ResponseInterface
    {
        //Define handler from request resource type.
    }
}
