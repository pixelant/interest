<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;

/**
 * Simply sets the language in the ContentObjectRenderer's data array.
 */
class ApplyFieldDataTransformations implements RecordOperationEventHandlerInterface
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

        $settings = $recordOperation->getSettings();

        foreach (
            $settings['transformations.'][$recordOperation->getTable() . '.'] ?? [] as $fieldName => $configuration
        ) {
            $fieldName = substr($fieldName, 0, -1);

            $recordOperation->setDataFieldForDataHandler(
                $fieldName,
                $recordOperation->getContentObjectRenderer()->stdWrap(
                    $recordOperation->getDataForDataHandler()[$fieldName] ?? '',
                    $configuration
                )
            );
        }
    }
}
