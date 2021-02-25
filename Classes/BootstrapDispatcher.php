<?php
declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Bootstrap\Core;
use Pixelant\Interest\Dispatcher\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 *
 * Main entrypoint for REST requests.
 */
class BootstrapDispatcher
{
    /**
     * @var ConfigurationManagerInterface
     */
    private $configurationManager;

    /**
     * @var Dispatcher
     */
    private Dispatcher $dispatcher;

    /**
     * @var array
     */
    private array $configuration;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager;

    /**
     * @var bool
     */
    private bool $isInitialized = false;

    /**
     * BootstrapDispatcher constructor.
     * @param ObjectManagerInterface|null $objectManager
     * @param array                  $configuration
     */
    public function __construct(ObjectManagerInterface $objectManager = null, array $configuration = [])
    {
        $this->objectManager = $objectManager;
        $this->configuration = $configuration;
    }

    /**
     * @param ServerRequestInterface $request
     */
    public function processRequest(ServerRequestInterface $request)
    {
        $this->bootstrap($request);

        $this->dispatcher->processRequest($request);
    }

    private function bootstrap(ServerRequestInterface $request)
    {
        if (!$this->isInitialized){
            \TYPO3\CMS\Core\Core\Bootstrap::initializeBackendUser();
            $GLOBALS['BE_USER']->backendCheckLogin();

            $this->initializeObjectManager();
            $this->initializeConfiguration($this->configuration);
            $this->initializeDispatcher();

            $this->isInitialized = true;
        }
    }

    private function initializeObjectManager()
    {
        if (!$this->objectManager){
            $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        }
    }

    /**
     * Initialize the Configuration Manager instance
     *
     * @param array $configuration
     */
    private function initializeConfiguration(array $configuration)
    {
        $this->configurationManager = $this->objectManager->get(ConfigurationManagerInterface::class);
        $this->configurationManager->setConfiguration($configuration);
    }

    private function initializeDispatcher(): void
    {
        $requestFactory = $this->objectManager->getRequestFactory();
        $responseFactory = $this->objectManager->getResponseFactory();

        $this->dispatcher = new Dispatcher($requestFactory, $responseFactory, $this->objectManager);
    }
}
