<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface;

/**
 * Sets the uniqueness hash of the record operation. E.g. used to stop if repeating an operation.
 *
 * @see StopIfRepeatingPreviousRecordOperation
 */
class GenerateRecordOperationHashEventHandler implements BeforeRecordOperationEventHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(BeforeRecordOperationEvent $event): void
    {
        $event->getRecordOperation()->setHash(
            md5(get_class($event->getRecordOperation()) . serialize($event->getRecordOperation()->getArguments()))
        );
    }
}
