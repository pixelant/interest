<?php

declare(strict_types=1);

namespace Pixelant\Interest\Hook;

use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Ensure remote ID entry is deleted if the record for the remote ID is deleted.
 */
class ProcessCmdmap
{
    /**
     * @param string $command
     * @param string $table
     * @phpstan-ignore-next-line
     * @param $id
     * @phpstan-ignore-next-line
     * @param $value
     * @param DataHandler $dataHandler
     * @phpstan-ignore-next-line
     * @param $pasteUpdate
     * @phpstan-ignore-next-line
     * @param $pasteDatamap
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        $id,
        $value,
        DataHandler $dataHandler,
        $pasteUpdate,
        $pasteDatamap
    ) {
        if ($command === 'delete') {
            /** @var RemoteIdMappingRepository $mappingRepository */
            $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

            $remoteId = $mappingRepository->getRemoteId($table, $id);

            if ($remoteId !== false) {
                $mappingRepository->remove($remoteId);
            }
        }
    }
}
