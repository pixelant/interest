<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * If a record has been successfully created, we can remove the pending relations records that were pointing to it. They
 * were processed earlier, but we couldn't remove them until we knew the record had been successfully created.
 *
 * @see AddResolvedPendingRelationsToDataHandler
 */
class RemovePendingRelationsForCreatedRecord implements RecordOperationEventHandlerInterface
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
