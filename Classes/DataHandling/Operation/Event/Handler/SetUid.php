<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;

/**
 * Sets the UID in the operation if it was successful.
 */
class SetUid implements RecordOperationEventHandlerInterface
{
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if (
            $event->getRecordOperation()->isSuccessful()
            && $event->getRecordOperation() instanceof CreateRecordOperation
            && $event->getRecordOperation()->getUid() === 0
        ) {
            $dataHandler = $event->getRecordOperation()->getDataHandler();

            $event->getRecordOperation()->setUid(
                $dataHandler->substNEWwithIDs[array_key_first($dataHandler->substNEWwithIDs)]
            );
        }
    }
}
