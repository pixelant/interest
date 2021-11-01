<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Handle a record creation operation.
 */
class CreateRecordOperation extends AbstractRecordOperation
{
    public function __construct(
        array $data,
        string $table,
        string $remoteId,
        ?string $language = null,
        ?string $workspace = null,
        ?array $metaData = []
    ) {
        if (GeneralUtility::makeInstance(RemoteIdMappingRepository::class)->exists($remoteId)) {
            throw new IdentityConflictException(
                'The remote ID "' . $remoteId . '" already exists.',
                1635780292790
            );
        }

        parent::__construct($data, $table, $remoteId, $language, $workspace, $metaData);

        $uid = $this->getUid() ?: StringUtility::getUniqueId('NEW');

        $this->dataHandler->datamap[$table][$uid] = $this->getData();
    }
}
