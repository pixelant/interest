<?php
declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Handler\HandlerInterface;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ResponseFactoryInterface;
use Pixelant\Interest\RequestFactoryInterface;
use Pixelant\Interest\Controller\AccessControllerInterface;
use Pixelant\Interest\Configuration\ConfigurationProviderInterface;
use Pixelant\Interest\Configuration\TypoScriptConfigurationProvider;
use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function get($class, ...$arguments)
    {
        return $this->container->get($class, ...$arguments);
    }

    /**
     * @return \Pixelant\Interest\ResponseFactoryInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function getResponseFactory(): ResponseFactoryInterface
    {
        return $this->get(ResponseFactoryInterface::class);
    }

    /**
     * @return \Pixelant\Interest\RequestFactoryInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function getRequestFactory(): RequestFactoryInterface
    {
        return $this->get(RequestFactoryInterface::class);
    }

    public function getAccessController(InterestRequestInterface $request = null): AccessControllerInterface
    {
        $resourceType = $request->getResourceType();
        list($vendor, $extension,) = Utility::getClassNamePartsForResourceType($resourceType);

        // Check if an extension provides a Authentication Provider
        $accessControllerClass = ($vendor ? $vendor . '\\' : '') . $extension . '\\Rest\\AccessController';
        if (!class_exists($accessControllerClass)) {
            /** @deprecated Class overrides without namespaces are deprecated. Will be removed in 5.0 */
            $accessControllerClass = 'Tx_' . $extension . '_Rest_AccessController';
        }

        return $this->get($accessControllerClass);
    }

    public function getHandler(InterestRequestInterface $request = null): HandlerInterface
    {
        $resourceType = $request->getResourceType();

    }

    public function getConfigurationProvider(): ConfigurationProviderInterface
    {
        if (!$this->configurationProvider) {
            $this->configurationProvider = $this->get(ConfigurationProviderInterface::class);
        }

        return $this->configurationProvider;
    }
}
