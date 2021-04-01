<?php

declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Cache\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendUserAuthentication extends \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
{
    /**
     * @param string $identifier
     * @throws \TYPO3\CMS\Core\Cache\Exception\DuplicateIdentifierException
     */
    public function cacheUser(string $identifier): void
    {
        $backendCache = GeneralUtility::makeInstance(Typo3DatabaseBackend::class, 'BE');
        $frontendCache = GeneralUtility::makeInstance(VariableFrontend::class, $identifier, $backendCache);
        $user = serialize($this);
        $frontendCache->set($identifier, $user);
    }
}
