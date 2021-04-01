<?php

declare(strict_types=1);

namespace Pixelant\Interest;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendUserAuthentication extends \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
{
    public const CACHE_TABLE = 'tx_interest_api_token';

    /**
     * @param string $identifier
     * @throws \TYPO3\CMS\Core\Cache\Exception\DuplicateIdentifierException
     */
    public function cacheUser(string $identifier): void
    {
        $user = serialize($this);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::CACHE_TABLE);

        $queryBuilder
            ->update(self::CACHE_TABLE)
            ->set('cached_data', $user)
            ->where(
                $queryBuilder->expr()->eq('token', $queryBuilder->createNamedParameter($identifier))
            )
            ->execute();
    }
}
