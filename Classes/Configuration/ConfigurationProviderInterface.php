<?php
declare(strict_types=1);

namespace Pixelant\Interest\Configuration;

use Pixelant\Interest\Domain\Model\ResourceType;

interface ConfigurationProviderInterface
{
    /**
     * The request want's to write data
     */
    const ACCESS_METHOD_WRITE = 'write';
    /**
     * The request want's to read data
     */
    const ACCESS_METHOD_READ = 'read';

    /**
     * @return array
     */
    public function getExtensionConfiguration(): array;

    /**
     * Returns true if logging is enabled.
     *
     * @return bool
     */
    public function isLoggingEnabled(): bool;


    /**
     * Returns true if logging (of execution time) should be done in response headers.
     *
     * @return bool
     */
    public function isHeaderLoggingEnabled(): bool;

    /**
     * Returns true if logging (of execution time, request, and response data) should be done in database.
     *
     * @return bool
     */
    public function isDatabaseLoggingEnabled(): bool;

    /**
     * Returns the lower limit in execution time above which logging is enabled.
     *
     * @return int The number of milliseconds
     */
    public function getLoggingMinimumExecutionTime(): int;

    /**
     * Returns true if logging is enabled and the supplied $milliseconds is higher or equal to the execution time limit.
     *
     * @param int $milliseconds
     * @return bool
     */
    public function isLoggingEnabledForExecutionTime(int $milliseconds): bool;

    /**
     * Returns the setting with the given key
     *
     * @param string $keyPath
     * @param mixed  $defaultValue
     * @return mixed
     */
    public function getSetting(string $keyPath, $defaultValue = null);

    /**
     * Returns the settings read from the TypoScript
     *
     * @return array
     */
    public function getSettings(): array;

    /**
     * Returns the paths configured in the settings
     *
     * @return ResourceConfiguration[]
     */
    public function getConfiguredResources(): array;

    /**
     * Returns the configuration matching the given resource type
     *
     * @param ResourceType $resourceType
     * @return ResourceConfiguration|null
     */
    public function getResourceConfiguration(ResourceType $resourceType): ?ResourceConfiguration;
}
