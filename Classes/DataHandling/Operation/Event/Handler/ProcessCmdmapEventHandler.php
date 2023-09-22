<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\DataHandlerSuccessMessage;

/**
 * Instructs DataHandler to process the cmdmap array.
 */
class ProcessCmdmapEventHandler implements AfterRecordOperationEventHandlerInterface
{
    public function __invoke(AfterRecordOperationEvent $event): void
    {
        if (count($event->getRecordOperation()->getDataHandler()->cmdmap) > 0) {
            $event->getRecordOperation()->getDataHandler()->process_cmdmap();

            $event->getRecordOperation()->dispatchMessage(new DataHandlerSuccessMessage(
                count($event->getRecordOperation()->getDataHandler()->errorLog) === 0
            ));
        }
    }
}
