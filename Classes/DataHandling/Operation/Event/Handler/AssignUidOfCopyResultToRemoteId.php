<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CopyRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Assigns the UID of a new record created by CopyRecordOperation to the intended remote ID.
 */
class AssignUidOfCopyResultToRemoteId implements RecordOperationEventHandlerInterface
{
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if (
            $event->getRecordOperation()->isSuccessful()
            && $event->getRecordOperation() instanceof CopyRecordOperation
        ) {
            $dataHandler = $event->getRecordOperation()->getDataHandler();

            $table = $event->getRecordOperation()->getTable();

            $id = $event->getRecordOperation()->getUid();

            $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

            $mappingRepository->add(
                $event->getRecordOperation()->getResultingRemoteId(),
                $table,
                $dataHandler->copyMappingArray_merged[$table][$id],
                $event->getRecordOperation()
            );
        }
    }
}
