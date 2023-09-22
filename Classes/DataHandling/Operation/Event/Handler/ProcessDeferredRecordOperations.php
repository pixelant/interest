<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\BeforeRecordOperationEventException;
use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\DeferredRecordOperationRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ProcessDeferredRecordOperations implements RecordOperationEventHandlerInterface
{
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        /** @var DeferredRecordOperationRepository $repository */
        $repository = GeneralUtility::makeInstance(DeferredRecordOperationRepository::class);

        $previousHash = '';

        foreach ($repository->get($event->getRecordOperation()->getRemoteId()) as $deferredRow) {
            $deferredRowClassParents = class_parents($deferredRow['class']);

            if (!is_array($deferredRowClassParents)) {
                $deferredRowClassParents = [];
            }

            if (
                $previousHash !== $deferredRow['_hash']
                && $deferredRow['class'] !== DeleteRecordOperation::class
                && !in_array(DeleteRecordOperation::class, $deferredRowClassParents, true)
            ) {
                $previousHash = $deferredRow['_hash'];

                try {
                    try {
                        $deferredOperation = new $deferredRow['class']($deferredRow['arguments']);
                    } catch (IdentityConflictException $exception) {
                        if (
                            $deferredRow['class'] === CreateRecordOperation::class
                            || in_array(CreateRecordOperation::class, $deferredRowClassParents, true)
                        ) {
                            $deferredOperation = new UpdateRecordOperation(... $deferredRow['arguments']);
                        } else {
                            throw $exception;
                        }
                    }

                    $deferredOperation();
                    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                } catch (BeforeRecordOperationEventException $exception) {
                    // Ignore stop exception when processing deferred operations.
                }
            }

            $repository->delete($deferredRow['uid']);
        }
    }
}
