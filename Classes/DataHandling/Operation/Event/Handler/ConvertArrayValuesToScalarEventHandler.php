<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface;
use Pixelant\Interest\Utility\RelationUtility;

/**
 * Converts all array values to comma-separated values.
 */
class ConvertArrayValuesToScalarEventHandler implements AfterRecordOperationEventHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(AfterRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        foreach ($event->getRecordOperation()->getDataForDataHandler() as $fieldName => $fieldValue) {
            if (!is_array($fieldValue)) {
                continue;
            }

            $event->getRecordOperation()->setDataFieldForDataHandler(
                $fieldName,
                RelationUtility::reduceArrayToScalar($event->getRecordOperation()->getTable(), $fieldName, $fieldValue)
            );
        }
    }
}
