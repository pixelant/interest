<?php

declare(strict_types=1);

namespace Pixelant\Interest\EventHandler;

use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Resource\Event\AfterFileDeletedEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeleteRemoteIdForDeletedFileEventHandler
{
    public function __invoke(AfterFileDeletedEvent $event)
    {
        static::removeRemoteIdForFile($event->getFile());
    }

    /**
     * Removes the file.
     *
     * Can be moved into __invoke once we drop support for TYPO3 v9.
     *
     * @param File $file
     */
    public static function removeRemoteIdForFile(FileInterface $file): void
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
