<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\PendingRelationMessage;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\TcaUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Filters out pending relations (relations to records not yet created) and issues a PendingRelationMessage for handling
 * later.
 */
class FilterPendingRelations implements RecordOperationEventHandlerInterface
{
    protected RemoteIdMappingRepository $mappingRepository;

    protected AbstractRecordOperation $recordOperation;

    /**
     * @inheritDoc
     */
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $this->recordOperation = $event->getRecordOperation();

        foreach ($this->recordOperation->getDataForDataHandler() as $fieldName => $fieldValue) {
            // Skip non-relational fields.
            if (!is_array($fieldValue)) {
                continue;
            }

            $this->processField($fieldName, $fieldValue);
        }
    }

    protected function processField(string $fieldName, array $fieldValue)
    {
        $filteredRelations = [];

        $pendingRelations = [];

        $prefixTableToUid = TcaUtility::hasRelationToMultipleTables($this->recordOperation->getTable(), $fieldName);

        foreach ($fieldValue as $remoteIdRelation) {
            if ($this->mappingRepository->exists($remoteIdRelation)) {
                $uid = $this->mappingRepository->get($remoteIdRelation);

                if ($prefixTableToUid) {
                    $uid = $this->mappingRepository->table($remoteIdRelation) . '_' . $uid;
                }

                $filteredRelations[] = $uid;

                continue;
            }

            $pendingRelations[] = $remoteIdRelation;
        }

        if ($pendingRelations !== []) {
            $this->recordOperation->dispatchMessage(
                new PendingRelationMessage(
                    $this->recordOperation->getTable(),
                    $fieldName,
                    $pendingRelations
                )
            );
        }

        $this->recordOperation->setDataFieldForDataHandler($fieldName, $filteredRelations);
    }
}
