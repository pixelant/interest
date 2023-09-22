<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event;

/**
 * An event that is called at the very beginning of the initialization of an AbstractRecordOperation object. While the
 * AbstractRecordOperation is immutable from this context, you can throw a BeforeRecordOperationEventException to
 * stop the record operation.
 */
class RecordOperationSetupEvent extends AbstractRecordOperationEvent
{
}
