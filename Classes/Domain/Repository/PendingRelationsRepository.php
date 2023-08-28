<?php

declare(strict_types=1);

namespace Pixelant\Interest\Domain\Repository;

use Doctrine\DBAL\Driver\Result;
use Pixelant\Interest\Domain\Repository\Exception\InvalidQueryResultException;

/**
 * Database operations relating to pending relations to remote IDs that do not yet exist in the database.
 */
class PendingRelationsRepository extends AbstractRepository
{
    public const TABLE_NAME = 'tx_interest_pending_relations';

    /**
     * Get all pending.
     *
     * @param string $remoteId
     * @return array
     * @throws InvalidQueryResultException
     */
    public function get(string $remoteId): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'remote_id',
                    $queryBuilder->createNamedParameter($remoteId, \PDO::PARAM_STR)
                )
            )
            ->executeQuery();

        if (!($result instanceof Result)) {
            throw new InvalidQueryResultException(
                'Query result was not an instance of ' . Result::class,
                1648879655137
            );
        }

        return $result->fetchAllAssociative();
    }

    /**
     * Sets the relations for $field in record $uid in $table. Removes any existing records.
     *
     * @param string $table
     * @param string $field
     * @param int $uid
     * @param array $remoteIds
     */
    public function set(string $table, string $field, int $uid, array $remoteIds): void
    {
        $this->removeLocal($table, $field, $uid);

        $remoteIds = array_filter($remoteIds);

        foreach ($remoteIds as $remoteId) {
            $this->setSingle($table, $field, $uid, $remoteId);
        }
    }

    /**
     * Sets a relation for $field in record $uid in $table. Does NOT remove any existing records.
     *
     * @param string $table
     * @param string $field
     * @param int $uid
     * @param string $remoteId
     */
    public function setSingle(string $table, string $field, int $uid, string $remoteId)
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->insert(self::TABLE_NAME)
            ->values([
                'remote_id' => $remoteId,
                'table' => $table,
                'field' => $field,
                'record_uid' => $uid,
            ])
            ->executeStatement();
    }

    /**
     * Removes all existing pending relations for $field in record $uid in $table.
     *
     * @param string $table
     * @param string $field
     * @param int $uid
     */
    public function removeLocal(string $table, string $field, int $uid): void
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'table',
                    $queryBuilder->createNamedParameter($table)
                ),
                $queryBuilder->expr()->eq(
                    'field',
                    $queryBuilder->createNamedParameter($field)
                ),
                $queryBuilder->expr()->eq(
                    'record_uid',
                    $queryBuilder->createNamedParameter($uid)
                )
            )
            ->executeStatement();
    }

    /**
     * Removes all existing pending relations for $remoteId.
     *
     * @param string $remoteId
     */
    public function removeRemote(string $remoteId): void
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'remote_id',
                    $queryBuilder->createNamedParameter($remoteId)
                )
            )
            ->executeStatement();
    }
}
