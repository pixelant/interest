<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Exception\DataHandlerErrorException;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\DatabaseUtility;
use Pixelant\Interest\Utility\TcaUtility;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Checks MM relations from a recently created record and makes sure the record has the correct order in the list of
 * items on the remote side.
 *
 * @see RelationSortingAsMetaDataEventHandler
 */
class ForeignRelationSortingEventHandler implements AfterRecordOperationEventHandlerInterface
{
    protected ?RemoteIdMappingRepository $mappingRepository = null;

    /**
     * @inheritDoc
     */
    public function __invoke(AfterRecordOperationEvent $event): void
    {
        $this->event = $event;

        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $data = [];
        foreach ($this->getMmFieldConfigurations() as $fieldName => $fieldConfiguration) {
            $relationIds = $event->getRecordOperation()->getData()[$fieldName] ?? [];

            if (empty($relationIds)) {
                continue;
            }

            if (!is_array($relationIds)) {
                $relationIds = explode(',', $relationIds);
            }

            $foreignTable = $fieldConfiguration['foreign_table'] ?? null;
            if (
                $fieldConfiguration['type'] === 'group'
                && $fieldConfiguration['allowed'] !== '*'
                && strpos($fieldConfiguration['allowed'], ',') === false
            ) {
                $foreignTable = $fieldConfiguration['allowed'];
            }

            foreach ($relationIds as $relationId) {
                if ($fieldConfiguration['type'] === 'group' && $foreignTable === null) {
                    $parts = explode('_', $relationId);
                    $relationId = array_pop($parts);
                    $foreignTable = implode('_', $parts);
                }

                $data = array_merge_recursive(
                    $data,
                    $this->orderOnForeignSideOfRelation($foreignTable, (int)$relationId)
                );
            }
        }

        if (count($data) > 0) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();

            if (!empty($this->dataHandler->errorLog)) {
                throw new DataHandlerErrorException(
                    'Error occurred during foreign-side relation ordering in remote ID based on relations'
                    . ' from remote ID "' . $event->getRecordOperation()->getRemoteId() . '": '
                    . implode(', ', $this->dataHandler->errorLog)
                    . ' Datamap: ' . json_encode($this->dataHandler->datamap),
                    1641480842077
                );
            }
        }
    }

    /**
     * Returns the names of fields with an MM relation table.
     *
     * @param string $tableName
     * @return array
     */
    protected function getMmFieldConfigurations(): array
    {
        $recordOperation = $this->event->getRecordOperation();

        $persistedRecordData = DatabaseUtility::getRecord(
            $recordOperation->getTable(),
            $recordOperation->getUid()
        );

        $fieldConfigurations = [];
        foreach (array_keys($GLOBALS['TCA'][$recordOperation->getTable()]['columns']) as $fieldName) {
            $fieldConfiguration = TcaUtility::getTcaFieldConfigurationAndRespectColumnsOverrides(
                $recordOperation->getTable(),
                $fieldName,
                $persistedRecordData
            );

            if (!empty($fieldConfiguration['MM'] ?? '')) {
                $fieldConfigurations[$fieldName] = $fieldConfiguration;
            }
        }

        return $fieldConfigurations;
    }

    protected function orderOnForeignSideOfRelation(string $table, int $relationId): array
    {
        $foreignRemoteId = $this->mappingRepository->getRemoteId($table, $relationId);
        $localRemoteId = $this->event->getRecordOperation()->getRemoteId();

        if ($foreignRemoteId === false) {
            return [];
        }

        $orderingIntents = $this->mappingRepository->getMetaDataValue(
            $foreignRemoteId,
            RelationSortingAsMetaDataEventHandler::class
        ) ?? [];

        foreach ($orderingIntents as $fieldName => $orderingIntent) {
            if (in_array($localRemoteId, $orderingIntent)) {
                $fieldConfiguration = TcaUtility::getTcaFieldConfigurationAndRespectColumnsOverrides(
                    $table,
                    $fieldName,
                    DatabaseUtility::getRecord($table, $relationId)
                );

                /** @var RelationHandler $relationHandler */
                $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

                $relationHandler->start(
                    '',
                    $fieldConfiguration['type'] === 'group'
                        ? $fieldConfiguration['allowed']
                        : $fieldConfiguration['foreign_table'],
                    $fieldConfiguration['MM'],
                    $relationId,
                    $table,
                    $fieldConfiguration
                );

                $relations = $relationHandler->getFromDB();

                $prefixTable = (
                    $fieldConfiguration['type'] === 'group'
                    && (
                        $fieldConfiguration['allowed'] === '*'
                        || strpos($fieldConfiguration['foreign_table'], ',') !== false
                    )
                );

                $flattenedRelations = [];
                foreach ($relations as $relationTable => $relation) {
                    if (!$prefixTable) {
                        $flattenedRelations = array_column($relation, 'uid');

                        break;
                    }

                    $flattenedRelations = array_map(
                        function (int $item) use ($relationTable) {
                            return $relationTable . '_' . $item;
                        },
                        array_column($relation, 'uid')
                    );
                }

                $orderedUids = [];
                foreach ($orderingIntent as $remoteIdToOrder) {
                    $uid = $this->mappingRepository->get($remoteIdToOrder);

                    if ($uid === 0) {
                        continue;
                    }

                    if (!$prefixTable) {
                        $orderedUids[] = $uid;

                        continue;
                    }

                    $orderedUids[] = $this->mappingRepository->table($remoteIdToOrder) . '_' . $uid;
                }

                $orderedRelations = array_merge(
                    $orderedUids,
                    array_diff($orderedUids, $flattenedRelations)
                );

                // Save some time by not updating correctly ordered arrays.
                if ($orderedUids === array_slice($orderedRelations, 0, count($orderedUids))) {
                    return [];
                }

                return [
                    $table => [
                        (string)$relationId => [
                            $fieldName => $orderedRelations,
                        ],
                    ],
                ];
            }
        }

        return [];
    }
}
