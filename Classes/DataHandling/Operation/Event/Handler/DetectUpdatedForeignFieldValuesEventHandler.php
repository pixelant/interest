<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\UpdatedForeignFieldValueMessage;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Utility\TcaUtility;

/**
 * Check datamap fields with foreign field and store value(s) in array. After process_datamap values can be used to
 * compare what is actually stored in the database, and we can delete removed values.
 */
class DetectUpdatedForeignFieldValuesEventHandler implements AfterRecordOperationEventHandlerInterface
{
    public function __invoke(AfterRecordOperationEvent $event): void
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
                    $recordOperation->dispatchMessage(new UpdatedForeignFieldValueMessage(
                        $recordOperation->getTable(),
                        $field,
                        $id,
                        $value
                    ));
                }
            }
        }
    }
}
