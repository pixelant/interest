<?php

declare(strict_types=1);

namespace Pixelant\Interest\Configuration;

use Pixelant\Interest\Domain\Model\ResourceType;
use Pixelant\Interest\Utility\Utility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\Exception\InvalidTypeException;

class AbstractConfigurationProvider implements ConfigurationProviderInterface
{
    /**
     * @var array|null
     */
    protected ?array $settings = null;

    /**
     * @var array
     */
    protected array $extensionConfiguration;

    /**
     * Constructor. Initializes extension configuration and overrides values from environment.
     */
    public function __construct()
    {
        try {
            $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('interest');
        } catch (ExtensionConfigurationExtensionNotConfiguredException $exception) {
            $this->extensionConfiguration = [];
        }

        $this->extensionConfiguration['log'] =
            (int)(getenv('APP_INTEREST_LOG') ?? $this->extensionConfiguration['log']);
        $this->extensionConfiguration['logMs'] =
            (int)(getenv('APP_INTEREST_LOG_MS') ?? $this->extensionConfiguration['logMs']);
    }

    /**
     * @return array
     */
    public function getExtensionConfiguration(): array
    {
        return $this->extensionConfiguration;
    }

    /**
     * Returns true if logging is enabled.
     *
     * @return bool
     */
    public function isLoggingEnabled(): bool
    {
        return (bool)$this->extensionConfiguration['log'];
    }

    /**
     * Returns true if logging (of execution time) should be done in response headers.
     *
     * @return bool
     */
    public function isHeaderLoggingEnabled(): bool
    {
        return (bool)($this->extensionConfiguration['log'] & 1);
    }

    /**
     * Returns true if logging (of execution time, request, and response data) should be done in database.
     *
     * @return bool
     */
    public function isDatabaseLoggingEnabled(): bool
    {
        return (bool)($this->extensionConfiguration['log'] & 2);
    }

    /**
     * Returns the lower limit in execution time above which logging is enabled.
     *
     * @return int The number of milliseconds
     */
    public function getLoggingMinimumExecutionTime(): int
    {
        return $this->extensionConfiguration['logMs'];
    }

    /**
     * Returns true if logging is enabled and the supplied $milliseconds is higher or equal to the execution time limit.
     *
     * @param int $milliseconds
     * @return bool
     */
    public function isLoggingEnabledForExecutionTime(int $milliseconds): bool
    {
        return $this->isLoggingEnabled() && $milliseconds >= $this->getLoggingMinimumExecutionTime();
    }

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
        if (null === $matchingSetting && null !== $defaultValue) {
            return $defaultValue;
        }

        return $matchingSetting;
    }

    /**
     * Returns the configuration matching the given resource type.
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
                    null === $resourceTypeString ? 'null' : $resourceTypeString
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
     * Check if the given pattern matches the resource type.
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

        return preg_match("!^${currentPathPattern}$!", (string)$resourceTypeString);
    }

    /**
     * Returns the paths configured in the settings.
     *
     * @return ResourceConfiguration[]
     * @throws InvalidConfigurationException
     */
    public function getConfiguredResources(): array
    {
        $configurationCollection = [];
        foreach ($this->getRawConfiguredResourceTypes() as $path => $configuration) {
            [$configuration, $normalizeResourceType] = $this->preparePath($configuration, $path);

            /** @codingStandardsIgnoreStart */
            $readAccess = isset($configuration[self::ACCESS_METHOD_READ]) ? new Access($configuration[self::ACCESS_METHOD_READ]) : Access::denied();
            $writeAccess = isset($configuration[self::ACCESS_METHOD_WRITE]) ? new Access($configuration[self::ACCESS_METHOD_WRITE]) : Access::denied();
            // @codingStandardsIgnoreEnd

            if (isset($configuration['className'])) {
                throw new InvalidConfigurationException('Unsupported configuration key "className"');
            }

            $resourceType = new ResourceType($normalizeResourceType);

            $configurationCollection[$normalizeResourceType] = new ResourceConfiguration(
                $resourceType,
                $readAccess,
                $writeAccess,
                $configuration['handlerClass'] ?? '',
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

        return $settings['paths.'] ?? [];
    }

    /**
     * If no explicit path is configured use the current key.
     *
     * @param array  $configuration
     * @param string $path
     * @return array
     */
    private function preparePath(array $configuration, $path)
    {
        $resourceType = $configuration['path'] ?? trim($path, '.');
        $normalizeResourceType = Utility::normalizeResourceType($resourceType);
        $configuration['path'] = $normalizeResourceType;

        return [$configuration, $normalizeResourceType];
    }

    /**
     * Fetch aliases for the given Resource Type.
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
