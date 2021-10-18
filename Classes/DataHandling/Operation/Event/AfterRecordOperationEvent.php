<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation\Event;


use Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation;

/**
 * Event called after the completion of an AbstractRecordOperation.
 *
 */
class AfterRecordOperationEvent
{
    protected AbstractRecordOperation $recordOperation;

    /**
     * @param AbstractRecordOperation $recordOperation
     */
    public function __construct(AbstractRecordOperation $recordOperation) {
        $this->recordOperation = $recordOperation;
    }

    /**
     * @return AbstractRecordOperation
     */
    public function getRecordOperation(): AbstractRecordOperation
    {
        return $this->recordOperation;
    }
}
