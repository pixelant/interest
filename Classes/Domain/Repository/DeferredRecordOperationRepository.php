<?php

declare(strict_types=1);


namespace Pixelant\Interest\Domain\Repository;


use Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation;

/**
 *
 */
class DeferredRecordOperationRepository extends AbstractRepository
{
    public const TABLE_NAME = 'tx_interest_deferred_operation';

    /**
     * Add a record operation to the list of deferred operations.
     *
     * @param string $dependentRemoteId
     * @param AbstractRecordOperation $operation
     */
    public function add(string $dependentRemoteId, AbstractRecordOperation $operation)
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->insert(self::TABLE_NAME)
            ->values([
                'crdate' => time(),
                'dependent_remote_id' => $dependentRemoteId,
                'class' => get_class($operation),
                'arguments' => serialize($operation->getArguments())
            ])
            ->execute();
    }

    /**
     * Get all deferred operations waiting for $dependentRemoteId.
     *
     * @param string $dependentRemoteId
     * @return array
     */
    public function get(string $dependentRemoteId): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq(
                'dependent_remote_id',
                $queryBuilder->createNamedParameter($dependentRemoteId))
            )
            ->orderBy('crdate')
            ->execute()
            ->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === false) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['arguments'] = unserialize($row['arguments']);
        }

        return $rows;
    }

    /**
     * Delete a specific deferred operation, identified by its unique identifier.
     *
     * @param int $uid
     */
    public function delete(int $uid)
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq(
                'uid',
                $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
            )
            ->execute();
    }
}
