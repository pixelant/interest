<?php

declare(strict_types=1);


namespace Pixelant\Interest\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\RelationHandler;
use Pixelant\Interest\DataHandling\DataHandler;
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
    )
    {
        /** @var RelationHandler $relationHandler */
        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

        $relationHandler->start(
            '',
            $foreignTable,
            '',
            $pendingRelation['record_uid'],
            $pendingRelation['table'],
            TcaUtility::getTcaFieldConfigurationAndRespectColumnsOverrides(
                $pendingRelation['table'],
                $pendingRelation['field'],
                DatabaseUtility::getRecord($pendingRelation['table'], $pendingRelation['record_uid'])
            )
        );

        $existingRelations = array_column(
            $relationHandler->getFromDB()[$pendingRelation['table']] ?? [],
            'uid'
        );

        $dataHandler->datamap[$pendingRelation['table']][$pendingRelation['record_uid']][$pendingRelation['field']]
            = implode(',', array_unique(array_merge($existingRelations, [$foreignUid])));
    }
}
