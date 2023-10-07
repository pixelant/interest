<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\RelationFieldValueMessage;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Utility\RelationUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Process updated foreign field values to find values to delete by adding them to cmdmap.
 */
class ProcessUpdatedForeignFieldValues implements RecordOperationEventHandlerInterface
{
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if (!($event->getRecordOperation() instanceof UpdateRecordOperation)) {
            return;
        }

        $recordOperation = $event->getRecordOperation();

        do {
            /** @var RelationFieldValueMessage $message */
            $message = $recordOperation->retrieveMessage(RelationFieldValueMessage::class);

            if ($message === null) {
                break;
            }

            $newValues = $message->getValue();

            if (!is_array($newValues)) {
                $newValues = GeneralUtility::trimExplode(',', $message->getValue(), true);
            }

            foreach ($this->getRelationsFromMessage($message) as $relationTable => $relationTableValues) {
                foreach ($relationTableValues as $relationRecordId) {
                    if (!in_array((string)$relationRecordId, $newValues, true)) {
                        $recordOperation->getDataHandler()->cmdmap[$relationTable][$relationRecordId]['delete'] = 1;
                    }
                }
            }
        } while (true);
    }

    /**
     * Wrapper for RelationUtility::getRelationsFromField(), mocked during testing.
     *
     * @param RelationFieldValueMessage $message
     * @return array
     * @internal
     */
    public function getRelationsFromMessage(RelationFieldValueMessage $message): array
    {
        return RelationUtility::getRelationsFromField(
            $message->getTable(),
            $message->getId(),
            $message->getField()
        );
    }
}
