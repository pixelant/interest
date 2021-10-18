<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface;
use Pixelant\Interest\Domain\Repository\DeferredRecordOperationRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ProcessDeferredRecordOperationsEventHandler implements AfterRecordOperationEventHandlerInterface
{
    public function __invoke(AfterRecordOperationEvent $event): void
    {
        /** @var DeferredRecordOperationRepository $repository */
        $repository = GeneralUtility::makeInstance(DeferredRecordOperationRepository::class);

        foreach ($repository->get($event->getRecordOperation()->getRemoteId()) as $deferredRow) {
            if (
                get_class($event->getRecordOperation()) !== DeleteRecordOperation::class
                || $deferredRow['class'] === DeleteRecordOperation::class
            ) {
                new $deferredRow['class'](... $deferredRow['arguments']);
            }

            $repository->delete($deferredRow['uid']);
        }
    }
}
