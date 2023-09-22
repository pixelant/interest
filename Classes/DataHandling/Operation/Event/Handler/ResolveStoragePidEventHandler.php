<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Attempts to resolve the storage PID.
 */
class ResolveStoragePidEventHandler implements BeforeRecordOperationEventHandlerInterface
{
    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    public function __invoke(BeforeRecordOperationEvent $event): void
    {
        if (
            !isset($event->getRecordOperation()->getDataForDataHandler()['pid'])
            && !($event->getRecordOperation() instanceof CreateRecordOperation)
        ) {
            return;
        }

        if (($GLOBALS['TCA'][$event->getRecordOperation()->getTable()]['ctrl']['rootLevel'] ?? null) === 1) {
            $event->getRecordOperation()->setStoragePid(0);

            return;
        }

        if (isset($event->getRecordOperation()->getDataForDataHandler()['pid'])) {
            $event->getRecordOperation()->setStoragePid(
                GeneralUtility::makeInstance(RemoteIdMappingRepository::class)
                    ->get((string)$event->getRecordOperation()->getDataForDataHandler()['pid'])
            );

            return;
        }

        $settings = $event->getRecordOperation()->getSettings();

        $pid = $event->getRecordOperation()->getContentObjectRenderer()->stdWrap(
            $settings['persistence.']['storagePid'] ?? '',
            $settings['persistence.']['storagePid.'] ?? []
        );

        if ($pid === null || $pid === '') {
            $pid = 0;
        }

        if (!MathUtility::canBeInterpretedAsInteger($pid)) {
            throw new InvalidArgumentException(
                'The PID "' . $pid . '" is invalid and must be an integer.',
                1634213325242
            );
        }

        $event->getRecordOperation()->setStoragePid((int)$pid);
    }
}
