<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event;

interface AfterRecordOperationEventHandlerInterface
{
    /**
     * Handle a AfterRecordOperationEvent.
     *
     * @param AfterRecordOperationEvent $event
     */
    public function __invoke(AfterRecordOperationEvent $event): void;
}
