<?php
declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Dispatcher\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ServerRequestInterface;

/**
 *
 * Main entrypoint for REST requests.
 */
class BootstrapDispatcher
{

    /**
     * @var Dispatcher
     */
    private Dispatcher $dispatcher;

    /**
     * @var bool
     */
    private bool $isInitialized;

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    public function processRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->bootstrap($request);

        $this->dispatcher->processRequest($request);
    }

    private function bootstrap(ServerRequestInterface $request)
    {
        if (!$this->isInitialized){
            $this->initializeDispatcher();
            $this->isInitialized = true;
        }
    }


    private function initializeDispatcher(): void
    {
        $requestFactory = GeneralUtility::makeInstance(RequestFactoryInterface::class);
        $responseFactory = GeneralUtility::makeInstance(ResponseFactoryInterface::class);

        $this->dispatcher = new Dispatcher($requestFactory, $responseFactory);
    }
}
