<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\CompatibilityUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Delete a record.
 */
class DeleteRecordOperation extends AbstractRecordOperation
{
    public function __construct(
        string $remoteId,
        ?string $language = null,
        ?string $workspace = null
    ) {
        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        if (!$this->mappingRepository->exists($remoteId)) {
            throw new NotFoundException(
                'The remote ID "' . $remoteId . '" doesn\'t exist.',
                1639057109294
            );
        }

        $this->remoteId = $remoteId;
        $this->metaData = $metaData ?? [];
        $this->table = $this->mappingRepository->table($remoteId);

        $this->pendingRelationsRepository = GeneralUtility::makeInstance(PendingRelationsRepository::class);

        $this->language = $this->resolveLanguage((string)$language);
        $this->uid = $this->resolveUid();

        try {
            CompatibilityUtility::dispatchEvent(new BeforeRecordOperationEvent($this));
        } catch (StopRecordOperationException $exception) {
            $this->operationStopped = true;

            throw $exception;
        }

        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->dataHandler->start([], []);

        $this->dataHandler->cmdmap[$this->getTable()][$this->getUid()]['delete'] = 1;

        $this->mappingRepository->remove($remoteId);
    }

}
