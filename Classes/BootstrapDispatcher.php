<?php
declare(strict_types=1);

namespace Pixelant\Interest;

use cogpowered\FineDiff\Granularity\Character;
use Pixelant\Interest\Dispatcher\Dispatcher;
use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Authentication\AuthenticationService;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
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
     * @param array $configuration
     */
    public function __construct(ObjectManagerInterface $objectManager = null, array $configuration = [])
    {
        $this->objectManager = $objectManager;
        $this->configuration = $configuration;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->bootstrap($request);

        return $this->dispatcher->processRequest($request);
    }

    private function bootstrap(ServerRequestInterface $request)
    {
        if (!$this->isInitialized){
            \TYPO3\CMS\Core\Core\Bootstrap::initializeLanguageObject();

            $this->initializeObjectManager();
            $this->initializeBackendAuthenticationService($request);
            $this->initializeConfiguration($this->configuration);
            $this->initializePageDoktypes();
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

    private function initializePageDoktypes()
    {
        $GLOBALS['PAGES_TYPES'] =
            [
                'default' => [
                    'allowedTables' => '',
                    'onlyAllowedTables' => false
                ]
            ];
    }

    /**
     * @param ServerRequestInterface $request
     */
    private function initializeBackendAuthenticationService(ServerRequestInterface $request)
    {
        $backendUserAuthentication = $this->objectManager->get(BackendUserAuthentication::class);
        $serverParams = $request->getServerParams();
        $username = null;
        $password = null;

        if ($serverParams["HTTP_AUTHORIZATION"]){
            list($username, $password) = explode( ':',base64_decode(substr($serverParams["HTTP_AUTHORIZATION"], 6)));
        }

        $_POST['login_status'] = 'login';
        $_POST['username'] = $username;
        $_POST['userident'] = password_hash($password, PASSWORD_ARGON2I);

        $GLOBALS['BE_USER'] = $backendUserAuthentication;
        $backendUserAuthentication->start();

        var_dump($GLOBALS['BE_USER']);
        die();
    }
}
