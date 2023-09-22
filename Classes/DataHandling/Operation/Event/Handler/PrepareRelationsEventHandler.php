<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Prepare relations in the data.
 *
 * All relations to records are either changed from the remote ID to the correct localID or marked as a pending
 * relation. Pending relation information is temporarily added to $this->pendingRelations and persisted using
 * persistPendingRelations().
 */
class PrepareRelationsEventHandler implements BeforeRecordOperationEventHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(BeforeRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        $recordOperation = $event->getRecordOperation();

        foreach ($recordOperation->getDataForDataHandler() as $fieldName => $fieldValue) {
            // Skip non-relational fields.
            if (!is_array($fieldValue)) {
                continue;
            }

            $fieldValue = array_filter($fieldValue);

            $recordOperation->setDataFieldForDataHandler($fieldName, implode(',', $fieldValue));
        }
    }
}
