<?php
declare(strict_types=1);

namespace Pixelant\Interest;

use cogpowered\FineDiff\Granularity\Character;
use Pixelant\Interest\Dispatcher\Dispatcher;
use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Authentication\AuthenticationService;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
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
            Bootstrap::initializeBackendUser();
            Bootstrap::initializeLanguageObject();
            $this->initializeObjectManager();

            $this->authenticateBackendUser($request);
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
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function authenticateBackendUser(ServerRequestInterface $request)
    {
        $cacheManager = $this->objectManager->get(CacheManager::class);

        if (!preg_match('/authentication/', $request->getRequestTarget())){
            $GLOBALS['BE_USER']->user = $cacheManager->getCache('userTS')->get('user');
            Bootstrap::initializeBackendAuthentication();
        }

        $queryBuilder = $this->objectManager->getQueryBuilder('be_users');
        $serverParams = $request->getServerParams();
        $username = null;
        $password = null;

        if ($serverParams["HTTP_AUTHORIZATION"]){
            list($username, $password) = explode( ':',base64_decode(substr($serverParams["HTTP_AUTHORIZATION"], 6)));
        }

        $user = $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('username', "'".$username."'")
            )
            ->execute()
            ->fetchAllAssociative();


        $passwordHashFactory = $this->objectManager->get(PasswordHashFactory::class);
        $hashClassForGivenPassword = $passwordHashFactory->get(
            $user[0]['password'],
            $GLOBALS['BE_USER']->loginType);

        $isMatch = $hashClassForGivenPassword->checkPassword($password, $user[0]['password']);

        if ($isMatch){
            $GLOBALS['BE_USER']->user = $user[0];
            Bootstrap::initializeBackendAuthentication();

            $backendInterface = $this->objectManager->get(Typo3DatabaseBackend::class, 'BE');
            $frontendInterface = $this->objectManager->get(VariableFrontend::class, 'userTS', $backendInterface);

            $frontendInterface->set('user', $GLOBALS['BE_USER']->user);

            $cacheManager->registerCache($frontendInterface);

            return true;
        } else {
            return false;
        }
    }
}
