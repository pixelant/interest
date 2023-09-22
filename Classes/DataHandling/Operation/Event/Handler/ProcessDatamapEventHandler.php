<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\DataHandlerSuccessMessage;

/**
 * Instructs DataHandler to process the datamap array.
 */
class ProcessDatamapEventHandler implements AfterRecordOperationEventHandlerInterface
{
    public function __invoke(AfterRecordOperationEvent $event): void
    {
        if (count($event->getRecordOperation()->getDataHandler()->datamap) > 0) {
            $event->getRecordOperation()->getDataHandler()->process_datamap();

            $event->getRecordOperation()->dispatchMessage(new DataHandlerSuccessMessage(
                count($event->getRecordOperation()->getDataHandler()->errorLog) === 0
            ));
        }
    }
}
