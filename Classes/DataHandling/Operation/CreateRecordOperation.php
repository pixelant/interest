<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Handle a record creation operation.
 */
class CreateRecordOperation extends AbstractConstructiveRecordOperation
{
    public function __construct(
        RecordRepresentation $recordRepresentation,
        ?array $metaData = []
    ) {
        $remoteId = $recordRepresentation->getRecordInstanceIdentifier()->getRemoteIdWithAspects();

        if (GeneralUtility::makeInstance(RemoteIdMappingRepository::class)->exists($remoteId)) {
            throw new IdentityConflictException(
                'The remote ID "' . $remoteId . '" already exists.',
                1635780292790
            );
        }

        parent::__construct($recordRepresentation, $metaData);

        if (!isset($this->getDataForDataHandler()['pid'])) {
            $this->setDataForDataHandler(
                array_merge(
                    $this->getDataForDataHandler(),
                    ['pid' => $this->getStoragePid()]
                )
            );
        }

        $uid = $this->getUid();

        if ($uid === 0) {
            $uid = StringUtility::getUniqueId('NEW');
        }

        $table = $recordRepresentation->getRecordInstanceIdentifier()->getTable();

        $this->dataHandler->datamap[$table][$uid] = $this->getDataForDataHandler();

        $this->resolvePendingRelations($uid);
    }

    public function __invoke()
    {
        parent::__invoke();

        $this->pendingRelationsRepository->removeRemote($this->getRemoteId());
    }
}
