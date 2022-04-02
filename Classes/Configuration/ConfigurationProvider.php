<?php

declare(strict_types=1);

namespace Pixelant\Interest\Configuration;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationProvider implements SingletonInterface
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

        $this->extensionConfiguration['log'] = (int)(
            getenv('APP_INTEREST_LOG') !== false
            ? getenv('APP_INTEREST_LOG')
            : $this->extensionConfiguration['log']
        );
        $this->extensionConfiguration['logMs'] = (int)(
            getenv('APP_INTEREST_LOG_MS') !== false
            ? getenv('APP_INTEREST_LOG_MS')
            : $this->extensionConfiguration['logMs']
        );
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
     * Returns the settings read from UserTS.
     *
     * @return array
     */
    public function getSettings(): array
    {
        if ($this->settings === null) {
            $this->settings = $GLOBALS['BE_USER']->getTsConfig()['tx_interest.'] ?? [];
        }

        return $this->settings;
    }
}
