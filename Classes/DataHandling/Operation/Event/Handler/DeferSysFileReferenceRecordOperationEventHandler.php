<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;


use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Defers a sys_file_reference operation if the local file has not yet been created.
 */
class DeferSysFileReferenceRecordOperationEventHandler extends AbstractDetermineDeferredRecordOperationEventHandler
{
    protected function getDependentRemoteId(): ?string
    {
        if (
            $this->getEvent()->getRecordOperation()->getTable() === 'sys_file_reference'
            && isset($this->getEvent()->getRecordOperation()->getData()['uid_local'])
        ) {
            return $this->getEvent()->getRecordOperation()->getData()['uid_local'];
        }

        return null;
    }
}
