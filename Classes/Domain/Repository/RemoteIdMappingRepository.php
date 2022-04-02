<?php

declare(strict_types=1);

namespace Pixelant\Interest\Domain\Repository;

use Doctrine\DBAL\Driver\ResultStatement;
use Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\Domain\Repository\Exception\InvalidQueryResultException;
use Pixelant\Interest\Utility\DatabaseUtility;
use Pixelant\Interest\Utility\TcaUtility;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Repository for interaction with the database table tx_interest_remote_id_mapping.
 */
class RemoteIdMappingRepository extends AbstractRepository
{
    public const TABLE_NAME = 'tx_interest_remote_id_mapping';

    public const LANGUAGE_ASPECT_PREFIX = '|||L';

    /**
     * @var array Cache of remoteId (key) to localId (value) mapping
     */
    protected static array $remoteToLocalIdCache = [];

    /**
     * @var array Cache of remoteId (key) to table (value) mapping
     */
    protected static array $remoteIdToTableCache = [];

    /**
     * @var array Meta data entries (value) for remote IDs (key) that have not yet been mapped to a UID.
     */
    protected static array $unmappedMetaDataEntries = [];

    /**
     * Get the local ID equivalent for $remoteId.
     *
     * @param string $remoteId
     * @return int Local ID. Zero if it doesn't exist.
     */
    public function get(string $remoteId, ?AbstractRecordOperation $recordOperation = null): int
    {
        $remoteId = $this->addAspectsToRemoteId($remoteId, $recordOperation);

        if (isset(self::$remoteToLocalIdCache[$remoteId])) {
            return (int)self::$remoteToLocalIdCache[$remoteId];
        }

        $queryBuilder = $this->getQueryBuilder();

        $result = $queryBuilder
            ->select('uid_local', 'table')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'remote_id',
                    $queryBuilder->createNamedParameter($remoteId, \PDO::PARAM_STR)
                )
            )
            ->execute();

        if (!($result instanceof ResultStatement)) {
            throw new InvalidQueryResultException(
                'Query result was not an instance of ' . ResultStatement::class,
                1648879827875
            );
        }

        $row = $result->fetchAssociative();

        self::$remoteToLocalIdCache[$remoteId] = (int)$row['uid_local'];
        self::$remoteIdToTableCache[$remoteId] = $row['table'];

        if (
            self::$remoteToLocalIdCache[$remoteId] > 0
            && DatabaseUtility::getRecord($row['table'], self::$remoteToLocalIdCache[$remoteId]) === null
        ) {
            $this->remove($remoteId);
        }

        return self::$remoteToLocalIdCache[$remoteId];
    }

    /**
     * Returns the table the remote ID is related to, or null if there is no such relation.
     *
     * @param string $remoteId
     * @return string|null
     */
    public function table(string $remoteId, ?AbstractRecordOperation $recordOperation = null): ?string
    {
        $remoteId = $this->addAspectsToRemoteId($remoteId, $recordOperation);

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
     * @param AbstractRecordOperation|null $recordOperation Must be set when called from within a record operation
     * @throws IdentityConflictException
     */
    public function add(
        string $remoteId,
        string $tableName,
        int $uid,
        ?AbstractRecordOperation $recordOperation = null
    ): void {
        $remoteId = $this->addAspectsToRemoteId($remoteId, $recordOperation);

        if ($this->exists($remoteId)) {
            throw new IdentityConflictException(
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
                'record_hash' => $recordOperation === null ? '' : $this->hashRecordOperation($recordOperation),
                'crdate' => time(),
                'tstamp' => time(),
                'touched' => time(),
                'metadata' => json_encode(self::$unmappedMetaDataEntries[$remoteId] ?? []),
            ])
            ->execute();

        self::$remoteToLocalIdCache[$remoteId] = $uid;
        self::$remoteIdToTableCache[$remoteId] = $tableName;
    }

    /**
     * Update the `touched` property timestamp. This timestamp indicates when a $remoteId's record changed or would have
     * changed if the hash wasn't the same.
     *
     * @param string $remoteId
     */
    public function touch(string $remoteId): void
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->update(self::TABLE_NAME)
            ->set('touched', time())
            ->where($queryBuilder->expr()->eq(
                'remote_id',
                $queryBuilder->createNamedParameter($remoteId)
            ))
            ->execute();
    }

    /**
     * Returns the timestamp when the remote ID was last touched or zero if it hasn't been touched or doesn't exist.
     *
     * @param string $remoteId
     * @return int timestamp
     */
    public function touched(string $remoteId): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return (int)$queryBuilder
            ->select('touched')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq(
                'remote_id',
                $queryBuilder->createNamedParameter($remoteId)
            ))
            ->execute()
            ->fetchColumn();
    }

    /**
     * Returns remote IDs that have not been touched since $timestamp.
     *
     * @param int $timestamp
     * @param bool $excludeManual Exclude manual entries. Since they are managed in the backend they are usually never
     *                            touched.
     * @return string[] Remote IDs
     */
    public function findAllUntouchedSince(int $timestamp, bool $excludeManual = true): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder->where($queryBuilder->expr()->lt('touched', $timestamp));

        if ($excludeManual) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('manual', 0));
        }

        return $queryBuilder
            ->select('remote_id')
            ->from(self::TABLE_NAME)
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN, 0) ?: [];
    }

    /**
     * Returns remote IDs that have been touched since $timestamp.
     *
     * @param int $timestamp
     * @param bool $excludeManual Exclude manual entries. Since they are managed in the backend they are usually never
     *                            touched.
     * @return string[] Remote IDs
     */
    public function findAllTouchedSince(int $timestamp, bool $excludeManual = true): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder->where($queryBuilder->expr()->lt('touched', $timestamp));

        if ($excludeManual) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('manual', 0));
        }

        return $queryBuilder
            ->select('remote_id')
            ->from(self::TABLE_NAME)
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN, 0) ?: [];
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
    public function remove(string $remoteId, ?AbstractRecordOperation $recordOperation = null): void
    {
        $remoteId = $this->addAspectsToRemoteId($remoteId, $recordOperation);

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
     * @param int $uid Must be base language UID (language UID equals zero).
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
            ->set('record_hash', $this->hashRecordOperation($recordOperation))
            ->set('tstamp', time())
            ->set('touched', time())
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

        $this->touch($recordOperation->getRemoteId());

        $queryBuilder = $this->getQueryBuilder();

        return (bool)$queryBuilder
            ->count('remote_id')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'remote_id',
                    $queryBuilder->createNamedParameter($recordOperation->getRemoteId())
                ),
                $queryBuilder->expr()->eq(
                    'record_hash',
                    $queryBuilder->createNamedParameter($this->hashRecordOperation($recordOperation))
                )
            )
            ->execute()
            ->fetchOne();
    }

    /**
     * Returns data from the `metadata` field, used to hold internal data used for data retrieval optimization etc.
     *
     * @param string $remoteId
     * @return array
     */
    public function getMetaData(string $remoteId): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $metaData = $queryBuilder
            ->select('metadata')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'remote_id',
                    $queryBuilder->createNamedParameter($remoteId, \PDO::PARAM_STR)
                )
            )
            ->execute()
            ->fetchOne();

        if ($metaData === false) {
            return [];
        }

        $metaData = json_decode($metaData, true) ?? [];

        if (!is_array($metaData)) {
            return [];
        }

        return $metaData;
    }

    /**
     * Retrieve a meta data value from the `metadata` field, used to hold internal data used for data retrieval
     * optimization etc.
     *
     * @param string $remoteId
     * @param string $key
     * @return string|float|int|array|null Null if value wasn't found
     */
    public function getMetaDataValue(string $remoteId, string $key)
    {
        return $this->getMetaData($remoteId)[$key] ?? null;
    }

    /**
     * Set a meta data value used to hold internal data used for data retrieval optimization etc.
     *
     * @param string $remoteId
     * @param string $key
     * @param string|float|int|array|null $value
     */
    public function setMetaDataValue(string $remoteId, string $key, $value)
    {
        $recordExists = $this->exists($remoteId);

        if (!$recordExists) {
            $metaData = self::$unmappedMetaDataEntries[$remoteId] ?? [];
        } else {
            $metaData = $this->getMetaData($remoteId);
        }

        $metaData[$key] = $value;

        if (!$recordExists) {
            self::$unmappedMetaDataEntries[$remoteId] = $metaData;

            return;
        }

        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->update(self::TABLE_NAME)
            ->set('metadata', json_encode($metaData))
            ->set('tstamp', time())
            ->where($queryBuilder->expr()->eq(
                'remote_id',
                $queryBuilder->createNamedParameter($remoteId)
            ))
            ->execute();
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

    /**
     * Adds aspects, such as language and workspace ID to a remote ID based on the $recordOperation. If the
     * $recordOperation is null, language null or language ID zero  , the $remoteId is removed unchanged.
     *
     * @param string $remoteId
     * @param AbstractRecordOperation|null $recordOperation
     * @return string
     */
    public function addAspectsToRemoteId(string $remoteId, ?AbstractRecordOperation $recordOperation): string
    {
        if (
            $recordOperation === null
            || !TcaUtility::isLocalizable($recordOperation->getTable())
            || $recordOperation->getLanguage() === null
            || $recordOperation->getLanguage()->getLanguageId() === 0
        ) {
            return $remoteId;
        }

        $languageAspect = self::LANGUAGE_ASPECT_PREFIX . $recordOperation->getLanguage()->getLanguageId();

        if (strpos($remoteId, $languageAspect) !== false) {
            return $remoteId;
        }

        $remoteId = $this->removeAspectsFromRemoteId($remoteId);

        return $remoteId . $languageAspect;
    }

    /**
     * @param string $remoteId
     * @return string
     */
    public function removeAspectsFromRemoteId(string $remoteId): string
    {
        if (strpos($remoteId, self::LANGUAGE_ASPECT_PREFIX) === false) {
            return $remoteId;
        }

        return substr($remoteId, 0, strpos($remoteId, self::LANGUAGE_ASPECT_PREFIX));
    }
}
