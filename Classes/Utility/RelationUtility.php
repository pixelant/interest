<?php

declare(strict_types=1);

namespace Pixelant\Interest\Utility;

use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\UpdateCountOnForeignSideOfInlineRecordEventHandler;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Convenience functions related to relations.
 */
class RelationUtility
{
    /**
     * @param DataHandler $dataHandler
     * @param array $pendingRelation
     * @param string $foreignTable
     * @param string|int $foreignUid
     */
    public static function addResolvedPendingRelationToDataHandler(
        DataHandler $dataHandler,
        array $pendingRelation,
        string $foreignTable,
        $foreignUid
    ) {
        /** @var RelationHandler $relationHandler */
        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

        $row = DatabaseUtility::getRecord($pendingRelation['table'], $pendingRelation['record_uid']);

        if ($row === null) {
            return;
        }

        $relationHandler->start(
            '',
            $foreignTable,
            '',
            $pendingRelation['record_uid'],
            $pendingRelation['table'],
            TcaUtility::getTcaFieldConfigurationAndRespectColumnsOverrides(
                $pendingRelation['table'],
                $pendingRelation['field'],
                $row
            )
        );

        $existingRelations = array_column(
            $relationHandler->getFromDB()[$pendingRelation['table']] ?? [],
            'uid'
        );

        $dataHandler->datamap[$pendingRelation['table']][$pendingRelation['record_uid']][$pendingRelation['field']]
            = implode(',', array_unique(array_merge($existingRelations, [$foreignUid])));
    }

    /**
     * Returns the relations from a record field.
     *
     * @param string $table The table name
     * @param int $uid The record UID
     * @param string $field The field name
     * @param array $rowWithRecordType Real or simulated record data. Must at least contain type value.
     * @return array
     */
    public static function getRelationsFromField(
        string $table,
        int $uid,
        string $field,
        array $rowWithRecordType = []
    ): array {
        return self::getRelationsFromFieldConfiguration(
            $table,
            $uid,
            TcaUtility::getTcaFieldConfigurationAndRespectColumnsOverrides(
                $table,
                $field,
                $rowWithRecordType
            )
        );
    }

    /**
     * Returns the relations from a record field based on the field configuration.
     *
     * @param string $table
     * @param int $uid
     * @param array $fieldConfig
     * @return array
     */
    public static function getRelationsFromFieldConfiguration(
        string $table,
        int $uid,
        array $fieldConfig
    ): array {
        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

        $relationHandler->start(
            '',
            $fieldConfig['foreign_table'],
            '',
            $uid,
            $table,
            $fieldConfig
        );

        return $relationHandler->tableArray;
    }

    /**
     * Returns the UIDs of records that share the same parent. Including the record $localUid itself.
     *
     * @param string $localTable
     * @param int $localUid
     * @param string $foreignTable
     * @param string $foreignField
     * @param int|string|null $recordType
     * @return array[] One key and one value. Key is the foreign table record UID and value is an array of relations.
     */
    public static function getSiblingRelationsOfForeignParent(
        string $localTable,
        int $localUid,
        string $foreignTable,
        string $foreignField,
        $recordType = null
    ): array {
        $rowWithTypeField = [];

        $typeField = TcaUtility::getTypeFieldForTable($foreignTable);

        if ($typeField !== null || $recordType === null) {
            $rowWithTypeField = [$typeField => $recordType];
        }

        $parentTableFieldConfig = TcaUtility::getTcaFieldConfigurationAndRespectColumnsOverrides(
            $foreignTable,
            $foreignField,
            $rowWithTypeField
        );

        /** @var RelationHandler $relationHandler */
        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

        $relationHandler->setFetchAllFields(true);

        $relationHandler->start(
            $localUid,
            $localTable,
            $parentTableFieldConfig['MM'] ?? '',
            0,
            $foreignTable,
            $parentTableFieldConfig
        );

        $relationHandler->getFromDB();

        $result = $relationHandler->results[$localTable][$localUid];

        if ($result[$parentTableFieldConfig['foreign_table_field']] !== $foreignTable) {
            return [];
        }

        $relations = self::getRelationsFromFieldConfiguration(
            $foreignTable,
            $result[$parentTableFieldConfig['foreign_field']],
            $parentTableFieldConfig
        );

        if (!in_array($localUid, $relations[$localTable])) {
            return [];
        }

        return [$result[$parentTableFieldConfig['foreign_field']] => $relations];
    }

    /**
     * Update the relation count for inline (IRRE) fields in parent records to this record.
     *
     * @see UpdateCountOnForeignSideOfInlineRecordEventHandler
     *
     * @param string $localTable
     * @param int $localUid
     * @param int $countModifier Number to add or subtract from the count.
     */
    public static function updateParentRecordInlineFieldRelationCount(
        string $localTable,
        int $localUid,
        int $countModifier = 0
    ) {
        $inlineRelations = TcaUtility::getInlineRelationsToTable($localTable);

        if (count($inlineRelations) === 0) {
            return;
        }

        foreach ($inlineRelations as $foreignTable => $fields) {
            foreach ($fields as $foreignFieldName => $recordTypes) {
                foreach ($recordTypes as $recordType) {
                    $result = self::getSiblingRelationsOfForeignParent(
                        $localTable,
                        $localUid,
                        $foreignTable,
                        $foreignFieldName,
                        $recordType
                    );

                    $foreignUid = array_key_first($result);
                    $relations = $result[$foreignUid];

                    if (count($relations) === 0) {
                        continue;
                    }

                    $count = array_reduce(
                        $relations,
                        function (int $carry, array $records) {
                            return $carry + count($records);
                        },
                        0
                    );

                    $queryBuilder = DatabaseUtility::getQueryBuilderForTable($foreignTable);

                    $queryBuilder
                        ->update($foreignTable)
                        ->set($foreignFieldName, $count + $countModifier)
                        ->where(
                            $queryBuilder->expr()->eq(
                                'uid',
                                $foreignUid
                            )
                        )
                        ->execute();
                }
            }
        }
    }
}
