<?php

declare(strict_types=1);

namespace Pixelant\Interest\Configuration;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class TypoScriptConfigurationProvider.
 */
class TypoScriptConfigurationProvider extends AbstractConfigurationProvider implements SingletonInterface
{
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
