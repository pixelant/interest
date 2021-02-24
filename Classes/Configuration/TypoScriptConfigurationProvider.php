<?php
declare(strict_types=1);

namespace Pixelant\Interest\Configuration;

use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;

/**
 * Class TypoScriptConfigurationProvider
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
    public function injectConfigurationManager(ConfigurationManager $configurationManager)
    {
        $this->configurationManager = $configurationManager;
    }

    /**
     * Returns the settings read from the TypoScript
     *
     * @return array
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    public function getSettings(): array
    {
        if ($this->settings === null) {
            $this->settings = [];

            $typoScript = $this->configurationManager->getConfiguration(
                ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK
            );
            if (isset($typoScript['plugin.'])
                && isset($typoScript['plugin.']['tx_interest.'])
                && isset($typoScript['plugin.']['tx_interest.']['settings.'])
            ) {
                $this->settings = $typoScript['plugin.']['tx_interest.']['settings.'];
            }
        }

        var_dump($typoScript);
        die();
        return $this->settings;
    }
}
