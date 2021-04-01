<?php

declare(strict_types=1);

namespace Pixelant\Interest\Hook;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ClearCachePostProc
{
    public const CACHE_TABLE = 'tx_interest_api_token';

    public function clearCachePostProc(&$params, &$pObj): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::CACHE_TABLE);

        $queryBuilder
            ->update(self::CACHE_TABLE)
            ->set('cached_data', '')
            ->execute();
    }
}
