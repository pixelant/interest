<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event;

interface BeforeRecordOperationEventHandlerInterface
{
    /**
     * Handle a BeforeRecordOperationEvent.
     *
     * @param BeforeRecordOperationEvent $event
     */
    public function __invoke(BeforeRecordOperationEvent $event): void;
}
