<?php

declare(strict_types=1);


namespace Pixelant\Interest\Domain\Repository;

/**
 * Database operations relating to pending relations to remote IDs that do not yet exist in the database.
 */
class PendingRelationsRepository extends AbstractRepository
{
    public const TABLE_NAME = 'tx_interest_pending_relations';

    /**
     * Sets the relations for $field in record $uid in $table. Removes any existing records.
     *
     * @param string $table
     * @param string $field
     * @param int $uid
     * @param array $remoteIds
     */
    public function set(string $table, string $field, int $uid, array $remoteIds)
    {
        $this->remove($table, $field, $uid);

        foreach ($remoteIds as $remoteId) {
            $queryBuilder = $this->getQueryBuilder();

            $queryBuilder
                ->insert(self::TABLE_NAME)
                ->values([
                    'remote_id' => $remoteId,
                    'table' => $table,
                    'field' => $field,
                    'record_uid' => $uid,
                ])
                ->execute();
        }
    }

    /**
     * Removes all existing pending relations for $field in record $uid in $table.
     *
     * @param string $table
     * @param string $field
     * @param int $uid
     */
    public function remove(string $table, string $field, int $uid)
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'table',
                    $queryBuilder->createNamedParameter($table), \PDO::PARAM_STR
                ),
                $queryBuilder->expr()->eq(
                    'field', $queryBuilder->createNamedParameter($field),
                    \PDO::PARAM_STR
                ),
                $queryBuilder->expr()->eq(
                    'record_uid',
                    $queryBuilder->createNamedParameter($uid), \PDO::PARAM_INT
                ),
            )
            ->execute();
    }
}
