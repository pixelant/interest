<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\UpdatedForeignFieldValueMessage;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Utility\RelationUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Process updated foreign field values to find values to delete by adding them to cmdmap.
 */
class ProcessUpdatedForeignFieldValuesEventHandler implements AfterRecordOperationEventHandlerInterface
{
    public function __invoke(AfterRecordOperationEvent $event): void
    {
        if (!($event->getRecordOperation() instanceof UpdateRecordOperation)) {
            return;
        }

        $recordOperation = $event->getRecordOperation();

        do {
            /** @var UpdatedForeignFieldValueMessage $message */
            $message = $recordOperation->retrieveMessage(UpdatedForeignFieldValueMessage::class);

            if ($message === null) {
                break;
            }

            $newValues = $message->getValue();

            if (!is_array($newValues)) {
                $newValues = GeneralUtility::trimExplode(',', $message->getValue(), true);
            }

            $fieldRelations = RelationUtility::getRelationsFromField(
                $message->getTable(),
                $message->getId(),
                $message->getField()
            );

            foreach ($fieldRelations as $relationTable => $relationTableValues) {
                foreach ($relationTableValues as $relationTableValue) {
                    if (!in_array((string)$relationTableValue, $newValues, true)) {
                        $recordOperation->getDataHandler()->cmdmap[$relationTable][$relationTableValue]['delete'] = 1;
                    }
                }
            }
        } while (true);
    }
}
