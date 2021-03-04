<?php
declare(strict_types=1);

namespace Pixelant\Interest\Configuration;

use TYPO3\CMS\Extbase\Utility\Exception\InvalidTypeException;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use Pixelant\Interest\Configuration\ConfigurationProviderInterface;
use Pixelant\Interest\Domain\Model\ResourceType;
use Pixelant\Interest\Utility\Utility;

class AbstractConfigurationProvider implements ConfigurationProviderInterface
{
    /**
     * @var array|null
     */
    protected ?array $settings = null;

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @param array $settings
     * @return AbstractConfigurationProvider
     */
    public function setSettings(array $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    /**
     * @param string $keyPath
     * @param null $defaultValue
     * @return mixed
     */
    public function getSetting(string $keyPath, $defaultValue = null)
    {
        $matchingSetting = $this->getSettings();

        $keyPathParts = explode('.', $keyPath);
        foreach ($keyPathParts as $key) {
            if (is_array($matchingSetting)) {
                if (isset($matchingSetting[$key . '.'])) {
                    $matchingSetting = $matchingSetting[$key . '.'];
                } elseif (isset($matchingSetting[$key])) {
                    $matchingSetting = $matchingSetting[$key];
                } else {
                    $matchingSetting = null;
                }
            } else {
                $matchingSetting = null;
            }
        }
        if (is_null($matchingSetting) && !is_null($defaultValue)) {
            return $defaultValue;
        }

        return $matchingSetting;
    }

    /**
     * Returns the configuration matching the given resource type
     *
     * @param ResourceType $resourceType
     * @return ResourceConfiguration
     * @throws InvalidTypeException
     * @throws InvalidConfigurationException
     */
    public function getResourceConfiguration(ResourceType $resourceType): ?ResourceConfiguration
    {
        $configuredPaths = $this->getConfiguredResources();
        $matchingConfiguration = null;
        $resourceTypeString = Utility::normalizeResourceType($resourceType);

        if (!$resourceTypeString) {
            throw new InvalidTypeException(
                sprintf(
                    'Invalid normalized Resource Type "%s"',
                    is_null($resourceTypeString) ? 'null' : $resourceTypeString
                )
            );
        }

        foreach ($configuredPaths as $configuration) {
            $currentResourceTypeString = (string)$configuration->getResourceType();
            if ('all' === $currentResourceTypeString && !$matchingConfiguration) {
                $matchingConfiguration = $configuration;
            } elseif ($this->checkIfPatternMatchesResourceType($currentResourceTypeString, $resourceTypeString)) {
                $matchingConfiguration = $configuration;
            }
        }

        if (null === $matchingConfiguration) {
            throw new InvalidConfigurationException(
                'No matching Resource Configuration found and "all" is not configured'
            );
        }

        return $matchingConfiguration;
    }

    /**
     * Check if the given pattern matches the resource type
     *
     * @param string $pattern
     * @param string $resourceTypeString
     * @return bool
     */
    private function checkIfPatternMatchesResourceType($pattern, $resourceTypeString)
    {
        $currentPathPattern = str_replace(
            '*',
            '\w*',
            str_replace('?', '\w', (string)$pattern)
        );

        return preg_match("!^$currentPathPattern$!", (string)$resourceTypeString);
    }

    /**
     * Returns the paths configured in the settings
     *
     * @return ResourceConfiguration[]
     * @throws InvalidConfigurationException
     */
    public function getConfiguredResources(): array
    {
        $configurationCollection = [];
        foreach ($this->getRawConfiguredResourceTypes() as $path => $configuration) {
            [$configuration, $normalizeResourceType] = $this->preparePath($configuration, $path);

            $readAccess = isset($configuration[self::ACCESS_METHOD_READ])
                ? new Access($configuration[self::ACCESS_METHOD_READ])
                : Access::denied();
            $writeAccess = isset($configuration[self::ACCESS_METHOD_WRITE])
                ? new Access($configuration[self::ACCESS_METHOD_WRITE])
                : Access::denied();

            if (isset($configuration['className'])) {
                throw new InvalidConfigurationException('Unsupported configuration key "className"');
            }

            $resourceType = new ResourceType($normalizeResourceType);

            $configurationCollection[$normalizeResourceType] = new ResourceConfiguration(
                $resourceType,
                $readAccess,
                $writeAccess,
                isset($configuration['handlerClass']) ? $configuration['handlerClass'] : '',
                $this->getAliasesForResourceType($resourceType)
            );
        }

        return $configurationCollection;
    }

    /**
     * @return array
     */
    private function getRawConfiguredResourceTypes()
    {
        $settings = $this->getSettings();
        if (isset($settings['paths']) && is_array($settings['paths'])) {
            return $settings['paths'];
        }

        return isset($settings['paths.']) ? $settings['paths.'] : [];
    }

    /**
     * If no explicit path is configured use the current key
     *
     * @param array  $configuration
     * @param string $path
     * @return array
     */
    private function preparePath(array $configuration, $path)
    {
        $resourceType = isset($configuration['path']) ? $configuration['path'] : trim($path, '.');
        $normalizeResourceType = Utility::normalizeResourceType($resourceType);
        $configuration['path'] = $normalizeResourceType;

        return [$configuration, $normalizeResourceType];
    }

    /**
     * Fetch aliases for the given Resource Type
     *
     * @param ResourceType $resourceType
     * @return string[]
     */
    private function getAliasesForResourceType(ResourceType $resourceType): array
    {
        $resourceTypeString = (string)$resourceType;

        return array_keys(
            array_filter(
                $this->getSetting('aliases', []),
                function ($alias) use ($resourceTypeString) {
                    // Return if the given Resource Type would handle this alias
                    return $this->checkIfPatternMatchesResourceType($resourceTypeString, $alias);
                }
            )
        );
    }
}
