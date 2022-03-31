<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event;

use Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation;

/**
 * An event that is called at the very beginning of the initialization of an AbstractRecordOperation object. While the
 * AbstractRecordOperation is immutable from this context, you can throw a BeforeRecordOperationEventException to
 * stop the record operation.
 */
class BeforeRecordOperationEvent
{
    protected AbstractRecordOperation $recordOperation;

    /**
     * @param AbstractRecordOperation $recordOperation
     */
    public function __construct(AbstractRecordOperation $recordOperation)
    {
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
