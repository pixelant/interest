<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Defers a record where the remote ID for a PID doesn't exist yet.
 */
class DeferMissingPidRemoteId extends AbstractDetermineDeferredRecordOperation
{
    protected function getDependentRemoteId(): ?string
    {
        $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $pid = $this->getEvent()->getRecordOperation()->getDataForDataHandler()['pid'][0] ?? null;

        if ($pid !== null && $mappingRepository->exists($pid)) {
            return null;
        }

        return $pid;
    }
}
