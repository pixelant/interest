<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Make a copy of a record.
 */
class CopyRecordOperation extends AbstractRecordOperation
{
    /**
     * @var string When copied, the new record will be assigned this remote ID.
     */
    protected string $resultingRemoteId;

    /**
     * @param RecordRepresentation $original The original record representation.
     * @param string $resultingRemoteId When copied, the new record will be assigned this remote ID.
     * @param RecordInstanceIdentifier|null $target
     * @throws NotFoundException If the remote ID we're copying from doesn't exist.
     * @throws InvalidArgumentException If the target table is not the same as $original or "pages".
     * @throws IdentityConflictException If the remote ID representing the resulting record already exists.
     * @throws StopRecordOperationException
     */
    public function __construct(
        RecordRepresentation $original,
        string $resultingRemoteId,
        ?RecordInstanceIdentifier $target = null
    ) {
        $this->resultingRemoteId = $resultingRemoteId;

        $target = $target ?? $original->getRecordInstanceIdentifier();

        $this->recordRepresentation = $original;

        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $originalRemoteId = $original->getRecordInstanceIdentifier()->getRemoteIdWithAspects();

        if (!$this->mappingRepository->exists($originalRemoteId)) {
            throw new NotFoundException(
                'The original\'s remote ID "' . $originalRemoteId . '" doesn\'t exist.',
                1702884437605
            );
        }

        if (!in_array($target->getTable(), ['pages', $original->getRecordInstanceIdentifier()->getTable()], true)) {
            throw new InvalidArgumentException(
                'The target record table can only be "pages" or "'
                . $original->getRecordInstanceIdentifier()->getTable() . '", but was "' . $target->getTable() . '".',
                1702884734179
            );
        }

        if ($this->mappingRepository->exists($resultingRemoteId)) {
            throw new IdentityConflictException(
                'The remote ID "' . $originalRemoteId . '" already exists.',
                1702883555460
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

        $targetId = $target->getUid();

        if ($target->getTable() !== 'pages') {
            $targetId = -$targetId;
        }

        $this->dataHandler->cmdmap[$this->getTable()][$this->getUid()]['copy'] = [
            'action' => 'paste',
            'target' => $targetId,
            'update' => $this->getDataForDataHandler(),
        ];
    }

    /**
     * @return string
     */
    public function getResultingRemoteId(): string
    {
        return $this->resultingRemoteId;
    }
}
