<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

/**
 * Defers a sys_file_reference operation if the local file has not yet been created.
 */
class DeferSysFileReferenceRecordOperationEventHandler extends AbstractDetermineDeferredRecordOperationEventHandler
{
    protected function getDependentRemoteId(): ?string
    {
        if (
            $this->getEvent()->getRecordOperation()->getTable() === 'sys_file_reference'
            && isset($this->getEvent()->getRecordOperation()->getDataForDataHandler()['uid_local'])
        ) {
            return $this->getEvent()->getRecordOperation()->getDataForDataHandler()['uid_local'];
        }

        return null;
    }
}
