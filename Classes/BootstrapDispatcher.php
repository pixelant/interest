<?php

declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\Dispatcher\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
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

    private function bootstrap(ServerRequestInterface $request): void
    {
        if (!$this->isInitialized) {
            Bootstrap::initializeBackendUser(BackendUserAuthentication::class);
            ExtensionManagementUtility::loadExtTables();
            Bootstrap::initializeLanguageObject();
            $this->initializeObjectManager();

            $this->authenticateBackendUser($request);
            $this->initializeConfiguration($this->configuration);
            $this->initializePageDoktypes();
            $this->initializeDispatcher();

            $this->isInitialized = true;
        }
    }

    private function initializeObjectManager(): void
    {
        if (!$this->objectManager) {
            $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        }
    }

    /**
     * Initialize the Configuration Manager instance.
     *
     * @param array $configuration
     */
    private function initializeConfiguration(array $configuration): void
    {
        $this->configurationManager = $this->objectManager->get(ConfigurationManagerInterface::class);
        $this->configurationManager->setConfiguration($configuration);
    }

    private function initializeDispatcher(): void
    {
        $requestFactory = $this->objectManager->getRequestFactory();
        $responseFactory = $this->objectManager->getResponseFactory();
        $configurationProvider = GeneralUtility::makeInstance(ConfigurationProvider::class);

        $this->dispatcher = new Dispatcher(
            $requestFactory,
            $responseFactory,
            $this->objectManager,
            $configurationProvider
        );
    }

    private function initializePageDoktypes(): void
    {
        $GLOBALS['PAGES_TYPES']
            = [
                'default' => [
                    'allowedTables' => '',
                    'onlyAllowedTables' => false,
                ],
            ];
    }

    /**
     * @param ServerRequestInterface $request
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function authenticateBackendUser(ServerRequestInterface $request): void
    {
        $serverParams = $request->getServerParams();
        $username = null;
        $password = null;
        $token = null;
        $cachedData = null;
        if ($serverParams['HTTP_AUTHENTICATION']) {
            $queryBuilder = $this->objectManager->getQueryBuilder('tx_interest_api_token');

            $tokenCount = $queryBuilder
                ->count('uid')
                ->from('tx_interest_api_token')
                ->where(
                    $queryBuilder->expr()->eq('token', "'" . $serverParams['HTTP_AUTHENTICATION'] . "'")
                )
                ->execute()
                ->fetchOne();

            if ($tokenCount > 0) {
                $userCredentials = $queryBuilder
                    ->select('be_user', 'password', 'cached_data', 'token')
                    ->from('tx_interest_api_token')
                    ->where(
                        $queryBuilder->expr()->eq('token', "'" . $serverParams['HTTP_AUTHENTICATION'] . "'")
                    )
                    ->execute()
                    ->fetchAllAssociative();

                $username = $userCredentials[0]['be_user'];
                $password = $userCredentials[0]['password'];
                $token = $userCredentials[0]['token'];
                $cachedData = $userCredentials[0]['cached_data'];
            } else {
                // @codingStandardsIgnoreStart
                [$username, $password] = explode(':', base64_decode(substr($serverParams['HTTP_AUTHENTICATION'], 6), true));
                // @codingStandardsIgnoreEnd
            }
        }

        if ($cachedData !== null && $cachedData !== '') {
            $beUser = unserialize($cachedData);
            $GLOBALS['BE_USER'] = $beUser;

            return;
        }

        $queryBuilder = $this->objectManager->getQueryBuilder('be_users');
        $user = $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('username', "'" . $username . "'")
            )
            ->execute()
            ->fetchAllAssociative();

        $passwordHashFactory = $this->objectManager->get(PasswordHashFactory::class);

        $hashClassForGivenPassword = $passwordHashFactory->get(
            $user[0]['password'],
            $GLOBALS['BE_USER']->loginType
        );

        $isMatch = $hashClassForGivenPassword->checkPassword($password, $user[0]['password']);

        if ($isMatch) {
            $GLOBALS['BE_USER']->user = $user[0];
            Bootstrap::initializeBackendAuthentication();

            if ($token !== null) {
                $GLOBALS['BE_USER']->cacheUser($token);
            }
        }
    }
}
