<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface;

/**
 * Sets the 'pid' key in the data array from the storage PID, if necessary.
 */
class SetPidEventHandler implements BeforeRecordOperationEventHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(BeforeRecordOperationEvent $event): void
    {
        if (
            !$event->getRecordOperation()->isDataFieldSet('pid')
            && $event->getRecordOperation() instanceof CreateRecordOperation
        ) {
            $event->getRecordOperation()->setDataFieldForDataHandler(
                'pid',
                $event->getRecordOperation()->getStoragePid()
            );
        }
    }
}
