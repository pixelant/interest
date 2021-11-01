<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Delete a record.
 */
class DeleteRecordOperation extends AbstractRecordOperation
{
    public function __construct(
        array $data,
        string $table,
        string $remoteId,
        ?string $language = null,
        ?string $workspace = null,
        ?array $metaData = []
    ) {
        if (!GeneralUtility::makeInstance(RemoteIdMappingRepository::class)->exists($remoteId)) {
            throw new NotFoundException(
                'The remote ID "' . $remoteId . '" doesn\'t exist.',
                1635780346047
            );
        }

        parent::__construct($data, $table, $remoteId, $language, $workspace, $metaData);

        $this->dataHandler->cmdmap[$table][$this->getUid()]['delete'] = 1;

        $this->mappingRepository->remove($remoteId);
    }

}
