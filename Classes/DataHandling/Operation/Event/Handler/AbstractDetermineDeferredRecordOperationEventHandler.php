<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface as EventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\Domain\Repository\DeferredRecordOperationRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract for event handlers dealing with deferred persistence. I.e. where a record operation should not be executed
 * now because other data needs to appear first. All subclasses must throw a DeferRecordOperationException.
 */
abstract class AbstractDetermineDeferredRecordOperationEventHandler implements EventHandlerInterface
{
    /**
     * @var AbstractRecordOperationEvent
     */
    protected $event;

    /**
     * Defers the operation if deferRecordOperation() returns true.
     *
     * @param AbstractRecordOperationEvent $event
     */
    final public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        $this->event = $event;

        $this->deferRecordOperation($this->getDependentRemoteId());
    }

    /**
     * Returns true if the current record operation should be deferred.
     *
     * @return string|null
     */
    abstract protected function getDependentRemoteId(): ?string;

    /**
     * Stores the deferred record operation.
     *
     * @throws StopRecordOperationException
     */
    final protected function deferRecordOperation(?string $dependentRemoteId)
    {
        if ($dependentRemoteId === null) {
            return;
        }

        /** @var RemoteIdMappingRepository $mappingRepository */
        $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        if ($mappingRepository->exists($dependentRemoteId)) {
            return;
        }

        /** @var DeferredRecordOperationRepository $deferredOperationRepository */
        $deferredOperationRepository = GeneralUtility::makeInstance(DeferredRecordOperationRepository::class);

        $deferredOperationRepository->add($dependentRemoteId, $this->getEvent()->getRecordOperation());

        throw new StopRecordOperationException(
            'Deferred record operation on remote ID "' . $this->getEvent()->getRecordOperation()->getRemoteId()
            . '". ' . ' Waiting for remote ID "' . $dependentRemoteId . '".',
            1634553398351
        );
    }

    /**
     * @return AbstractRecordOperationEvent
     */
    public function getEvent()
    {
        return $this->event;
    }
}
