<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationInvocationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\DataHandlerSuccessMessage;
use Pixelant\Interest\DataHandling\Operation\Exception\DataHandlerErrorException;
use Pixelant\Interest\DataHandling\Operation\Exception\IncompleteOperationException;
use Pixelant\Interest\DataHandling\Operation\Message\MessageInterface;
use Pixelant\Interest\DataHandling\Operation\Message\ReplacesPreviousMessageInterface;
use Pixelant\Interest\DataHandling\Operation\Message\RequiredMessageInterface;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Abstract class for handling record operations like create, delete, read, and update.
 */
abstract class AbstractRecordOperation
{
    /**
     * @var array
     */
    protected array $dataForDataHandler;

    /**
     * @var int
     */
    protected int $storagePid;

    /**
     * Language to use for processing.
     *
     * @var SiteLanguage|null
     */
    protected ?SiteLanguage $language;

    /**
     * @var ContentObjectRenderer
     */
    protected ContentObjectRenderer $contentObjectRenderer;

    /**
     * Additional data items not to be persisted but used in processing.
     *
     * @var array
     */
    protected array $metaData;

    /**
     * @var RemoteIdMappingRepository
     */
    protected RemoteIdMappingRepository $mappingRepository;

    /**
     * @var ConfigurationProvider
     */
    protected ConfigurationProvider $configurationProvider;

    /**
     * @var PendingRelationsRepository
     */
    protected PendingRelationsRepository $pendingRelationsRepository;

    /**
     * @var array
     */
    protected array $pendingRelations = [];

    /**
     * @var DataHandler
     */
    protected DataHandler $dataHandler;

    /**
     * Set to true if a DeferRecordOperationException is thrown. Means __destruct() will end early.
     *
     * @var bool
     */
    protected bool $operationStopped = false;

    /**
     * @var array|null
     */
    protected static ?array $getTypeValueCache = null;

    /**
     * The hash of this operation when it was initialized. Used to avoid repetition.
     *
     * @var string
     */
    protected string $hash;

    /**
     * @var RecordRepresentation
     */
    protected RecordRepresentation $recordRepresentation;

    /**
     * Is set to true or false if the DataHandler operations were successful. Will be null before the operations have
     * been executed.
     *
     * @var bool|null
     */
    protected ?bool $successful = null;

    /**
     * @var array<array<MessageInterface>>
     */
    protected array $messageQueue = [];

    /**
     * @var array
     */
    protected array $updatedForeignFieldValues = [];

    public function __invoke()
    {
        if ($this->operationStopped) {
            return;
        }

        GeneralUtility::makeInstance(EventDispatcher::class)->dispatch(new RecordOperationInvocationEvent($this));

        if ($this->isSuccessful() === false) {
            throw new DataHandlerErrorException(
                'Error occurred during the data handling: ' . implode(', ', $this->dataHandler->errorLog)
                . ' Datamap: ' . json_encode($this->dataHandler->datamap)
                . ' Cmdmap: ' . json_encode($this->dataHandler->cmdmap),
                1634296039450
            );
        }

        foreach (array_filter(array_values($this->messageQueue)) as $messageObject) {
            if ($messageObject instanceof RequiredMessageInterface) {
                throw new IncompleteOperationException(
                    'All required messages were not retrieved. Found: ' . get_class($messageObject),
                    1695260831
                );
            }
        }
    }

    /**
     * Returns the arguments as they would have been supplied to the constructor.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [
            $this->getDataForDataHandler(),
            $this->getTable(),
            $this->getRemoteId(),
            $this->getLanguage() === null ? null : $this->getLanguage()->getHreflang(),
            null,
            $this->getMetaData(),
        ];
    }

    /**
     * @return ContentObjectRenderer
     */
    protected function createContentObjectRenderer(): ContentObjectRenderer
    {
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $contentObjectRenderer->data = [
            'table' => $this->getTable(),
            'remoteId' => $this->getRemoteId(),
            'language' => null,
            'workspace' => null,
            'metaData' => $this->getMetaData(),
            'data' => $this->getDataForDataHandler(),
        ];

        return $contentObjectRenderer;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->recordRepresentation->getRecordInstanceIdentifier()->getTable();
    }

    /**
     * @return string
     */
    public function getRemoteId(): string
    {
        return $this->recordRepresentation->getRecordInstanceIdentifier()->getRemoteIdWithAspects();
    }

    /**
     * @return array
     */
    public function getDataForDataHandler(): array
    {
        return $this->dataForDataHandler;
    }

    /**
     * @param array $dataForDataHandler
     */
    public function setDataForDataHandler(array $dataForDataHandler)
    {
        $this->dataForDataHandler = $dataForDataHandler;
    }

    /**
     * Get the value of a specific field in the data for DataHandler.
     *
     * @param string $fieldName
     * @return mixed
     */
    public function getDataFieldForDataHandler(string $fieldName)
    {
        return $this->dataForDataHandler[$fieldName];
    }

    /**
     * Set the value of a specific field in the data for DataHandler.
     *
     * @param string $fieldName
     * @param string|int|float|array $value
     */
    public function setDataFieldForDataHandler(string $fieldName, $value)
    {
        $this->dataForDataHandler[$fieldName] = $value;
    }

    /**
     * Check if a field in the data array is set.
     *
     * @param string $fieldName
     * @return bool
     */
    public function isDataFieldSet(string $fieldName): bool
    {
        return isset($this->dataForDataHandler[$fieldName]);
    }

    /**
     * Unset a field in the data array.
     *
     * @param string $fieldName
     */
    public function unsetDataField(string $fieldName)
    {
        unset($this->dataForDataHandler[$fieldName]);
    }

    /**
     * @return int
     */
    public function getUid(): int
    {
        return $this->recordRepresentation->getRecordInstanceIdentifier()->getUid();
    }

    /**
     * @param int $uid
     */
    public function setUid(int $uid)
    {
        $this->recordRepresentation->getRecordInstanceIdentifier()->setUid($uid);
    }

    public function getUidPlaceholder(): string
    {
        return $this->recordRepresentation->getRecordInstanceIdentifier()->getUidPlaceholder();
    }

    /**
     * @return int
     */
    public function getStoragePid(): int
    {
        return $this->storagePid;
    }

    /**
     * @param int $storagePid
     */
    public function setStoragePid(int $storagePid)
    {
        $this->storagePid = $storagePid;
    }

    /**
     * @return SiteLanguage|null
     */
    public function getLanguage(): ?SiteLanguage
    {
        return $this->getRecordRepresentation()->getRecordInstanceIdentifier()->getLanguage();
    }

    /**
     * @return array
     */
    public function getMetaData(): array
    {
        return $this->metaData;
    }

    /**
     * @return ContentObjectRenderer
     */
    public function getContentObjectRenderer(): ContentObjectRenderer
    {
        return $this->contentObjectRenderer;
    }

    /**
     * Returns a standardized hash string representing the values of this invocation.
     *
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     */
    public function setHash(string $hash)
    {
        $this->hash = $hash;
    }

    /**
     * @return RecordRepresentation
     */
    public function getRecordRepresentation(): RecordRepresentation
    {
        return $this->recordRepresentation;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->configurationProvider->getSettings();
    }

    /**
     * Checks if there's an update in the DataHandler success status.
     */
    private function retrieveSuccessMessage()
    {
        /** @var DataHandlerSuccessMessage $message */
        $message = $this->retrieveMessage(DataHandlerSuccessMessage::class);

        if ($message !== null) {
            $this->successful = $message->isSuccess();
        }
    }

    /**
     * Returns true if the DataHandler operations have been executed.
     *
     * @return bool
     */
    public function hasExecuted(): bool
    {
        $this->retrieveSuccessMessage();

        return $this->successful !== null;
    }

    /**
     * Returns true if the DataHandler operations were successful. False if not. Null if not yet executed.
     *
     * @return bool|null
     */
    public function isSuccessful(): ?bool
    {
        $this->retrieveSuccessMessage();

        return $this->successful;
    }

    /**
     * @return DataHandler
     */
    public function getDataHandler(): DataHandler
    {
        return $this->dataHandler;
    }

    /**
     * Dispatch a message to be picked up later.
     *
     * @param MessageInterface $message
     */
    public function dispatchMessage(MessageInterface $message)
    {
        if ($message instanceof ReplacesPreviousMessageInterface) {
            $this->messageQueue[get_class($message)] = [$message];

            return;
        }

        if (!isset($this->messageQueue[get_class($message)])) {
            $this->messageQueue[get_class($message)] = [];
        }

        $this->messageQueue[get_class($message)][] = $message;
    }

    /**
     * Returns the most recent message and removes it from the queue.
     *
     * @param string $messageFqcn Fully qualified message class name
     * @return MessageInterface|null
     */
    public function retrieveMessage(string $messageFqcn): ?MessageInterface
    {
        if (!isset($this->messageQueue[$messageFqcn])) {
            return null;
        }

        return array_pop($this->messageQueue[$messageFqcn]);
    }
}
