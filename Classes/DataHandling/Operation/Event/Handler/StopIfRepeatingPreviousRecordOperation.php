<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Exception;

class StopIfRepeatingPreviousRecordOperation implements BeforeRecordOperationEventHandlerInterface
{
    /**
     * @param BeforeRecordOperationEvent $event
     * @throws Exception
     */
    public function __invoke(BeforeRecordOperationEvent $event): void
    {
        /** @var RemoteIdMappingRepository $repository */
        $repository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        if ($repository->isSameAsPrevious($event->getRecordOperation())) {
            throw new StopRecordOperationException(
                'Operation is same as previous operation, so we can skip this.',
                1634567803407
            );
        }
    }
}
