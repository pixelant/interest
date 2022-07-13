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
class CreateRecordOperation extends AbstractRecordOperation
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

        if (!isset($this->getData()['pid'])) {
            $this->setData(array_merge($this->getData(), ['pid' => $this->getStoragePid()]));
        }

        $uid = $this->getUid() ?: StringUtility::getUniqueId('NEW');
        $table = $recordRepresentation->getRecordInstanceIdentifier()->getTable();

        $this->dataHandler->datamap[$table][$uid] = $this->getData();

        $this->resolvePendingRelations($uid);
    }

    public function __invoke()
    {
        parent::__invoke();

        $this->pendingRelationsRepository->removeRemote($this->getRemoteId());
    }
}
