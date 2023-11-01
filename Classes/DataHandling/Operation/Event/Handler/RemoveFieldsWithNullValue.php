<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;

/**
 * Unsets fields with null value, so they don't create problems.
 */
class RemoveFieldsWithNullValue implements RecordOperationEventHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        $recordOperation = $event->getRecordOperation();

        foreach ($recordOperation->getDataForDataHandler() as $fieldName => $fieldValue) {
            if ($fieldValue === null) {
                $recordOperation->unsetDataField($fieldName);
            }
        }
    }
}
