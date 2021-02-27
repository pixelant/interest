<?php
declare(strict_types=1);

namespace Pixelant\Interest;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Pixelant\Interest\Authentication\AuthenticationProviderInterface;
use Pixelant\Interest\Authentication\UserProviderInterface;
use Pixelant\Interest\Handler\HandlerInterface;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\Controller\AccessControllerInterface;
use Pixelant\Interest\Configuration\ConfigurationProviderInterface;
use Pixelant\Interest\Configuration\TypoScriptConfigurationProvider;
use Pixelant\Interest\Router\RouterInterface;
use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Object\ObjectManager as TYPO3ObjectManager;

class ObjectManager implements ObjectManagerInterface
{
    /**
     * Configuration provider
     *
     * @var TypoScriptConfigurationProvider
     */
    protected $configurationProvider;

    /**
     * @var ContainerInterface|\TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     */
    protected $container;

    /**
     * Object Manager constructor
     *
     * @param ContainerInterface|\TYPO3\CMS\Extbase\Object\ObjectManagerInterface $container
     */
    public function __construct($container = null)
    {
        $this->container = $container ?: GeneralUtility::makeInstance(TYPO3ObjectManager::class);
    }

    /**
     * @param string $class
     * @param mixed ...$arguments
     * @return mixed|object
     * @throws Exception
     */
    public function get($class, ...$arguments)
    {
        return $this->container->get($class, ...$arguments);
    }

    /**
     * @return \Pixelant\Interest\ResponseFactoryInterface
     * @throws Exception
     */
    public function getResponseFactory(): ResponseFactoryInterface
    {
        return $this->get(ResponseFactoryInterface::class);
    }

    /**
     * @return \Pixelant\Interest\RequestFactoryInterface
     * @throws Exception
     */
    public function getRequestFactory(): RequestFactoryInterface
    {
        return $this->get(RequestFactoryInterface::class);
    }

    /**
     * @param InterestRequestInterface|null $request
     * @return AccessControllerInterface
     * @throws Exception
     */
    public function getAccessController(InterestRequestInterface $request = null): AccessControllerInterface
    {
        $objectManager = $this->get(ObjectManagerInterface::class);
        return $this->get(AccessControllerInterface::class, $objectManager);
    }

    /**
     * @param InterestRequestInterface|null $request
     * @return HandlerInterface
     * @throws Exception
     */
    public function getHandler(InterestRequestInterface $request = null): HandlerInterface
    {
        $resourceType = $request->getResourceType()->__toString();
        $configurationProvider = $this->getConfigurationProvider();
        $configuration = $configurationProvider->getSettings();
        $handler = null;

        foreach ($configuration['paths'] as $path => $value)
        {
            if ($path == $resourceType){
                $handlerClass = trim($value['handlerClass'], '\\');
                $handler = $this->get($handlerClass);
            }
        }

        if ($handler !== null && $handler instanceof HandlerInterface){
            return $handler;
        } else {
            throw new \UnexpectedValueException('Wrong handler class given.') ;
        }
    }

    /**
     * @return ConfigurationProviderInterface
     * @throws Exception
     */
    public function getConfigurationProvider(): ConfigurationProviderInterface
    {
        if (!$this->configurationProvider) {
            $this->configurationProvider = $this->get(ConfigurationProviderInterface::class);
        }

        return $this->configurationProvider;
    }

    /**
     * @return UserProviderInterface
     * @throws Exception
     */
    public function getUserProvider(): UserProviderInterface
    {
        return $this->get(UserProviderInterface::class);
    }

    /**
     * @return AuthenticationProviderInterface
     * @throws Exception
     */
    public function getAuthenticationProvider(): AuthenticationProviderInterface
    {
        $userProvider = $this->getUserProvider();
        $objectManager = $this->get(ObjectManagerInterface::class);
        return $this->get(AuthenticationProviderInterface::class, $userProvider, $objectManager);
    }

    /**
     * @return RouterInterface
     * @throws Exception
     */
    public function getRouter(): RouterInterface
    {
        return $this->get(RouterInterface::class);
    }

    /**
     * @param string $tableName
     * @return QueryBuilder
     * @throws Exception
     */
    public function getQueryBuilder(string $tableName): QueryBuilder
    {
        return $this->get(ConnectionPool::class)->getQueryBuilderForTable($tableName);
    }
}
