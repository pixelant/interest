<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface;

/**
 * Simply sets the language in the ContentObjectRenderer's data array.
 */
class ApplyFieldDataTransformationsEventHandler implements BeforeRecordOperationEventHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(BeforeRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        $recordOperation = $event->getRecordOperation();

        $settings = $recordOperation->getSettings();

        foreach (
            $settings['transformations.'][$recordOperation->getTable() . '.'] ?? [] as $fieldName => $configuration
        ) {
            $recordOperation->setDataFieldForDataHandler(
                $fieldName,
                $recordOperation->getContentObjectRenderer()->stdWrap(
                    $recordOperation()->getDataForDataHandler()[substr($fieldName, 0, -1)] ?? '',
                    $configuration
                )
            );
        }
    }
}
