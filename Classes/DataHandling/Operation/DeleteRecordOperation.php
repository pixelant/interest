<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Delete a record.
 */
class DeleteRecordOperation extends AbstractRecordOperation
{
    /**
     * @param RecordRepresentation $recordRepresentation
     * @throws \Pixelant\Interest\RequestHandler\Exception\NotFoundException
     * @throws StopRecordOperationException
     */
    public function __construct(RecordRepresentation $recordRepresentation)
    {
        $this->recordRepresentation = $recordRepresentation;

        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $remoteId = $recordRepresentation->getRecordInstanceIdentifier()->getRemoteIdWithAspects();

        if (!$this->mappingRepository->exists($remoteId)) {
            throw new NotFoundException(
                'The remote ID "' . $remoteId . '" doesn\'t exist.',
                1639057109294
            );
        }

        $this->metaData = [];
        $this->dataForDataHandler = [];

        $this->configurationProvider = GeneralUtility::makeInstance(ConfigurationProvider::class);

        $this->pendingRelationsRepository = GeneralUtility::makeInstance(PendingRelationsRepository::class);

        $this->contentObjectRenderer = $this->createContentObjectRenderer();

        try {
            GeneralUtility::makeInstance(EventDispatcher::class)->dispatch(new RecordOperationSetupEvent($this));
        } catch (StopRecordOperationException $exception) {
            $this->operationStopped = true;

            throw $exception;
        }

        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->dataHandler->start([], []);

        $this->dataHandler->cmdmap[$this->getTable()][$this->getUid()]['delete'] = 1;
    }
}
