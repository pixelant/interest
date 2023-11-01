<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event;

interface RecordOperationEventHandlerInterface
{
    /**
     * @param AbstractRecordOperationEvent $event
     */
    public function __invoke(AbstractRecordOperationEvent $event): void;
}
