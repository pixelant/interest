<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\PendingRelationMessage;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sets the UID in the operation if it was successful.
 */
class PersistPendingRelationInformationEventHandler implements AfterRecordOperationEventHandlerInterface
{
    public function __invoke(AfterRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        $repository = GeneralUtility::makeInstance(PendingRelationsRepository::class);

        do {
            /** @var PendingRelationMessage $message */
            $message = $event->getRecordOperation()->retrieveMessage(PendingRelationMessage::class);

            if ($message !== null) {
                $repository->set(
                    $message->getTable(),
                    $message->getField(),
                    $event->getRecordOperation()->getUid(),
                    $message->getRemoteIds()
                );
            }
        } while ($message !== null);
    }
}
