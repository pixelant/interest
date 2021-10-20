<?php

declare(strict_types=1);

namespace Pixelant\Interest\Slot;

use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Delete a file's remote ID when the file is deleted.
 */
class DeleteRemoteIdForDeletedFileSlot
{
    public function __invoke(AbstractFile $file)
    {
        if (!$file instanceof File) {
            return;
        }

        /** @var RemoteIdMappingRepository $mappingRepository */
        $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $remoteId = $mappingRepository->getRemoteId('sys_file', $file->getUid());

        if ($remoteId !== false) {
            $mappingRepository->remove($remoteId);
        }
    }

}
