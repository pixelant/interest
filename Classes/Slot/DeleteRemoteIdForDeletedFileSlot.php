<?php

declare(strict_types=1);

namespace Pixelant\Interest\Slot;

use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\EventHandler\DeleteRemoteIdForDeletedFileEventHandler;
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
        DeleteRemoteIdForDeletedFileEventHandler::removeRemoteIdForFile($file);
    }
}
