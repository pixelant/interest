<?php

declare(strict_types=1);

namespace Pixelant\Interest\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractRepository implements SingletonInterface
{
    public const TABLE_NAME = '';

    /**
     * Returns a QueryBuilder for self::TABLE_NAME.
     *
     * @return QueryBuilder
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(static::TABLE_NAME);
    }
}
