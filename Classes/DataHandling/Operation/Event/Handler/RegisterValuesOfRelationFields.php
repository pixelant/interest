<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\RelationFieldValueMessage;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Utility\TcaUtility;

/**
 * Check datamap fields keeping foreign relations and send a RelationFieldValueMessage for each. After process_datamap
 * the values can be used to compare what is actually stored in the database, and we can delete removed values.
 */
class RegisterValuesOfRelationFields implements RecordOperationEventHandlerInterface
{
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if (!($event->getRecordOperation() instanceof UpdateRecordOperation)) {
            return;
        }

        $recordOperation = $event->getRecordOperation();

        foreach ($recordOperation->getDataHandler()->datamap[$recordOperation->getTable()] as $id => $data) {
            foreach ($data as $field => $value) {
                $tcaFieldConf = TcaUtility::getTcaFieldConfigurationAndRespectColumnsOverrides(
                    $recordOperation->getTable(),
                    $field,
                    $recordOperation->getDataForDataHandler(),
                    $recordOperation->getRemoteId()
                );

                if ($tcaFieldConf['foreign_field'] ?? false) {
                    $recordOperation->dispatchMessage(
                        new RelationFieldValueMessage(
                            $recordOperation->getTable(),
                            $field,
                            $id,
                            $value
                        )
                    );
                }
            }
        }
    }
}
