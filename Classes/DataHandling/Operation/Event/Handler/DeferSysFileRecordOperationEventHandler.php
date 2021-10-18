<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Defers a sys_file operation if the sys_file has not yet been created.
 */
class DeferSysFileRecordOperationEventHandler extends AbstractDetermineDeferredRecordOperationEventHandler
{
    protected function getDependentRemoteId(): ?string
    {
        if ($this->getEvent()->getRecordOperation()->getTable() === 'sys_file') {
            return $this->getEvent()->getRecordOperation()->getRemoteId();
        }

        return null;
    }
}
