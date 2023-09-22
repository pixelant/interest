<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Utility\RelationUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sets the UID in the operation if it was successful.
 */
class ResolvePendingRelationsEventHandler implements AfterRecordOperationEventHandlerInterface
{
    public function __invoke(AfterRecordOperationEvent $event): void
    {
        if (!($event->getRecordOperation() instanceof CreateRecordOperation)) {
            return;
        }

        $repository = GeneralUtility::makeInstance(PendingRelationsRepository::class);

        foreach ($repository->get($event->getRecordOperation()->getRemoteId()) as $pendingRelation) {
            RelationUtility::addResolvedPendingRelationToDataHandler(
                $event->getRecordOperation()->getDataHandler(),
                $pendingRelation,
                $event->getRecordOperation()->getTable(),
                $event->getRecordOperation()->getUidPlaceholder()
            );
        }
    }
}
