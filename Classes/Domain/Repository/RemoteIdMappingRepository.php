<?php

declare(strict_types=1);


namespace Pixelant\Interest\Domain\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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
     * @var array Cache of remoteId (key) to localId (value) mapping
     */
    protected static array $remoteToLocalIdCache = [];

    /**
     * @var array Cache of remoteId (key) to table (value) mapping
     */
    protected static array $remoteIdToTableCache = [];

    /**
     * Get the local ID equivalent for $remoteId.
     *
     * @param string $remoteId
     * @return int Local ID. Zero if it doesn't exist.
     */
    public function get(string $remoteId): int
    {
        if (isset(self::$remoteToLocalIdCache[$remoteId])) {
            return (int)self::$remoteToLocalIdCache[$remoteId];
        }

        $queryBuilder = $this->getQueryBuilder();

        $row = $queryBuilder
            ->select('uid_local', 'table')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'remote_id',
                    $queryBuilder->createNamedParameter($remoteId, \PDO::PARAM_STR)
                )
            )
            ->execute()
            ->fetchAssociative();

        self::$remoteToLocalIdCache[$remoteId] = (int)$row['uid_local'];
        self::$remoteIdToTableCache[$remoteId] = $row['table'];

        if (BackendUtility::getRecord($row['table'], $row['uid_local']) === null) {
            $this->remove($remoteId);
        }

        return (int)self::$remoteToLocalIdCache[$remoteId];
    }

    /**
     * Returns the table the remote ID is related to, or null if there is no such relation.
     *
     * @param string $remoteId
     * @return string|null
     */
    public function table(string $remoteId): ?string
    {
        if (isset(self::$remoteIdToTableCache[$remoteId])) {
            return self::$remoteIdToTableCache[$remoteId];
        }

        $this->get($remoteId);

        return self::$remoteIdToTableCache[$remoteId] !== '' ? self::$remoteIdToTableCache[$remoteId] : null;
    }

    /**
     * @param string $remoteId
     * @param string $tableName
     * @param int $uid
     * @throws UniqueConstraintViolationException
     */
    public function add(string $remoteId, string $tableName, int $uid)
    {
        if ($this->exists($remoteId)) {
            throw new UniqueConstraintViolationException(
                'The remote ID "' . $remoteId . '" is already mapped.',
                1616582391
            );
        }

        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->insert(self::TABLE_NAME)
            ->values([
                'remote_id' => $remoteId,
                'table' => $tableName,
                'uid_local' => $uid
            ])
            ->execute();

        self::$remoteToLocalIdCache[$remoteId] = $uid;
        self::$remoteIdToTableCache[$remoteId] = $tableName;
    }

    /**
     * Checks if a relation to a local ID exists for remoteId
     *
     * @param string $remoteId
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function exists(string $remoteId): bool
    {
        return (bool)$this->get($remoteId);
    }

    /**
     * Remove an mapping from the table.
     *
     * @param string $remoteId
     */
    public function remove(string $remoteId)
    {
        self::$remoteToLocalIdCache[$remoteId] = 0;
        self::$remoteIdToTableCache[$remoteId] = '';

        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq(
                'remote_id',
                $queryBuilder->createNamedParameter($remoteId)
            ))
            ->execute();
    }
}
