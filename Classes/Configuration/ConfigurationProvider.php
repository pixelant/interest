<?php
declare(strict_types=1);

namespace Pixelant\Interest\Configuration;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Adds support for instance-wide configurations (extension configuration and envionment variables).
 *
 * Workaround to enable loading the correct implementation for `ConfigurationProviderInterface`
 */
class ConfigurationProvider extends TypoScriptConfigurationProvider
{
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
            (bool)(getenv('APP_INTEREST_LOG') ?? $this->extensionConfiguration['log']);
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
        return $this->extensionConfiguration['log'];
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
}
