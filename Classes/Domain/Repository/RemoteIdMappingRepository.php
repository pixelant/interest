<?php

declare(strict_types=1);

namespace Pixelant\Interest\Domain\Repository;

use Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Repository for interaction with the database table tx_interest_remote_id_mapping.
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
     * @param AbstractRecordOperation $recordOperation
     * @throws UniqueConstraintViolationException
     */
    public function add(string $remoteId, string $tableName, int $uid, AbstractRecordOperation $recordOperation): void
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
                'uid_local' => $uid,
                'record_hash' => $this->hashRecordOperation($recordOperation)
            ])
            ->execute();

        self::$remoteToLocalIdCache[$remoteId] = $uid;
        self::$remoteIdToTableCache[$remoteId] = $tableName;
    }

    /**
     * Checks if a relation to a local ID exists for remoteId.
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
    public function remove(string $remoteId): void
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

    /**
     * Getting mapped remoteId.
     *
     * @param string $table
     * @param int $uid
     * @return string|bool
     */
    public function getRemoteId(string $table, int $uid)
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('remote_id')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq('table', $queryBuilder->createNamedParameter($table)),
                    $queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($uid))
                )
            )
            ->execute()
            ->fetchOne();
    }

    /**
     * Updates the status hash of a remote ID so we can optimize and avoid duplicate updates.
     *
     * @param AbstractRecordOperation $recordOperation
     */
    public function update(AbstractRecordOperation $recordOperation)
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->update(self::TABLE_NAME)
            ->values([
                'record_hash' => $this->hashRecordOperation($recordOperation)
            ])
            ->where($queryBuilder->expr()->eq(
                'remote_id',
                $queryBuilder->createNamedParameter($recordOperation->getRemoteId())
            ))
            ->execute();
    }

    /**
     * Returns true if the $recordOperation is the same as last time we updated this remote ID.
     *
     * @param AbstractRecordOperation $recordOperation
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function isSameAsPrevious(AbstractRecordOperation $recordOperation): bool
    {
        if (!$this->exists($recordOperation->getRemoteId())) {
            return false;
        }

        $queryBuilder = $this->getQueryBuilder();

        return (bool)$queryBuilder
            ->count('remote_id')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'record_hash',
                    $queryBuilder->createNamedParameter($this->hashRecordOperation($recordOperation))
                )
            )
            ->execute()
            ->fetchOne();
    }

    /**
     * Returns and MD5 hash of a record operation.
     *
     * @param AbstractRecordOperation $recordOperation
     * @return string
     */
    protected function hashRecordOperation(AbstractRecordOperation $recordOperation): string
    {
        return md5(get_class($recordOperation) . serialize($recordOperation->getArguments()));
    }
}
