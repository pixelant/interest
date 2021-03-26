<?php

declare(strict_types=1);

namespace Pixelant\Interest\Configuration;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;

/**
 * Class TypoScriptConfigurationProvider.
 */
class TypoScriptConfigurationProvider extends AbstractConfigurationProvider
{
    /**
     * @var ConfigurationManager
     */
    protected ConfigurationManager $configurationManager;

    /**
     * @param ConfigurationManager $configurationManager
     */
    public function injectConfigurationManager(ConfigurationManager $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
    }

    /**
     * Returns the settings read from the TypoScript.
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    public function getSettings(): array
    {
        if ($this->settings === null) {
            $this->settings = [];

            $typoScript = $GLOBALS['BE_USER']->getTsConfig();

            if (isset($typoScript['tx_interest.'])) {
                $this->settings = $typoScript['tx_interest.'];
            }
        }

        return GeneralUtility::removeDotsFromTS($this->settings);
    }
}
