<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * If a record has been successfully deleted, we can remove its pending relations. We can't remove them until we know
 * that the operation has been successful.
 */
class RemovePendingRelationsForDeletedRecord implements RecordOperationEventHandlerInterface
{
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if (
            $event->getRecordOperation()->isSuccessful()
            && $event->getRecordOperation() instanceof DeleteRecordOperation
        ) {
            GeneralUtility::makeInstance(PendingRelationsRepository::class)
                ->removeLocal(
                    $event->getRecordOperation()->getTable(),
                    null,
                    $event->getRecordOperation()->getUid()
                );
        }
    }
}
