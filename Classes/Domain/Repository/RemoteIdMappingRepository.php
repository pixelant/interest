<?php

declare(strict_types=1);


namespace Pixelant\Interest\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository for interaction with the database table tx_interest_remote_id_mapping
 */
class RemoteIdMappingRepository extends AbstractRepository
{
    public const TABLE_NAME = 'tx_interest_remote_id_mapping';

    /**
     * Checks if a relation to a local ID exists for remoteId
     *
     * @param string $remoteId
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function exists(string $remoteId): bool
    {
        $queryBuilder = $this->getQueryBuilder();

        return (bool)$queryBuilder
            ->count('remote_id')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'remote_id',
                    $queryBuilder->createNamedParameter($remoteId, \PDO::PARAM_STR)
                )
            )
            ->execute()
            ->fetchOne();
    }
}
