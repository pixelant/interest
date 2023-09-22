<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sets the UID in the operation if it was successful.
 */
class MapRemoteId implements RecordOperationEventHandlerInterface
{
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        $recordOperation = $event->getRecordOperation();

        if (!$recordOperation->isSuccessful()) {
            return;
        }

        $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        if (
            $this instanceof CreateRecordOperation
            || (
                // The UID might have been set by another operation already (e.g. a file), but not added to mapping.
                !$mappingRepository->exists($recordOperation->getRemoteId())
                && $recordOperation->getUid() > 0
            )
        ) {
            $mappingRepository->add(
                $recordOperation->getRemoteId(),
                $recordOperation->getTable(),
                // This assumes we have only done a single operation and there is only one NEW key.
                // The UID might have been set by another operation already, even though this is CreateRecordOperation.
                $recordOperation->getUid(),
                $recordOperation
            );

            $recordOperation->setUid(
                $mappingRepository->get(
                    $recordOperation->getRecordRepresentation()->getRecordInstanceIdentifier()->getRemoteIdWithAspects()
                )
            );
        } else {
            $mappingRepository->update($recordOperation);
        }
    }
}
