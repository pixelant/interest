<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\DataHandlerSuccessMessage;

/**
 * Instructs DataHandler to process the datamap array.
 */
class ProcessDatamap implements RecordOperationEventHandlerInterface
{
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if (count($event->getRecordOperation()->getDataHandler()->datamap) > 0) {
            $event->getRecordOperation()->getDataHandler()->process_datamap();

            $event->getRecordOperation()->dispatchMessage(new DataHandlerSuccessMessage(
                count($event->getRecordOperation()->getDataHandler()->errorLog) === 0
            ));
        }
    }
}
