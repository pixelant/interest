<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sets the UID in the operation if it was successful.
 */
class CleanUpPendingRelationsEventHandler implements RecordOperationEventHandlerInterface
{
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if (
            $event->getRecordOperation()->isSuccessful()
            && $event->getRecordOperation() instanceof CreateRecordOperation
        ) {
            GeneralUtility::makeInstance(PendingRelationsRepository::class)
                ->removeRemote($event->getRecordOperation()->getRemoteId());
        }
    }
}
