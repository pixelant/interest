<?php

declare(strict_types=1);

namespace Pixelant\Interest\Utility;

use Doctrine\DBAL\Driver\Result;
use Pixelant\Interest\Domain\Repository\Exception\InvalidQueryResultException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DatabaseUtility
{
    /**
     * Optimized version of BackendUtility::getRecord
     *
     * @param string $table Table name present in $GLOBALS['TCA']
     * @param int $uid UID of record
     * @param array $fields List of fields to select
     * @return array|null Returns the row if found, otherwise NULL
     * @throws InvalidQueryResultException
     */
    public static function getRecord(string $table, int $uid, array $fields = ['*'])
    {
        // Ensure we have a valid uid (not 0 and not NEWxxxx) and a valid TCA
        if (!empty($GLOBALS['TCA'][$table])) {
            $queryBuilder = self::getQueryBuilderForTable($table);

            // do not use enabled fields here
            $queryBuilder->getRestrictions()->removeAll();
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

            // set table and where clause
            $queryBuilder
                ->select(...$fields)
                ->from($table)
                ->where($queryBuilder->expr()->eq('uid', (int)$uid));

            $result = $queryBuilder->execute();

            if (!($result instanceof Result)) {
                throw new InvalidQueryResultException(
                    'Query result was not an instance of ' . Result::class,
                    1648880491980
                );
            }

            $row = $result->fetchAssociative();

            if ($row) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param string $table
     * @return QueryBuilder
     */
    public static function getQueryBuilderForTable($table)
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    }
}
