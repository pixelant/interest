<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\DataHandling\Operation\Exception\MissingArgumentException;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\CompatibilityUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\FolderDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Intercepts a sys_file request to store the file data in the filesystem.
 */
class PersistFileDataEventHandler implements BeforeRecordOperationEventHandlerInterface
{
    protected RemoteIdMappingRepository $mappingRepository;

    protected ResourceFactory $resourceFactory;

    protected BeforeRecordOperationEvent $event;

    /**
     * @param BeforeRecordOperationEvent $event
     * @throws InvalidFileNameException
     * @throws IdentityConflictException
     */
    public function __invoke(BeforeRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        $this->event = $event;

        if ($this->event->getRecordOperation()->getTable() !== 'sys_file') {
            return;
        }

        $data = $this->event->getRecordOperation()->getData();

        $fileBaseName = $data['name'] ?? '';

        if (!CompatibilityUtility::getFileNameValidator()->isValid($fileBaseName)) {
            throw new InvalidFileNameException(
                'Invalid file name: "' . $fileBaseName . '"',
                1634664683340
            );
        }

        $settings = GeneralUtility::makeInstance(ConfigurationProvider::class)->getSettings();

        $storagePath = $this->event->getRecordOperation()->getContentObjectRenderer()->stdWrap(
            $settings['persistence.']['fileUploadFolderPath'],
            $settings['persistence.']['fileUploadFolderPath.'] ?? []
        );

        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $storage = $this->resourceFactory->getStorageObjectFromCombinedIdentifier($storagePath);

        $downloadFolder = $this->getDownloadFolder($storagePath, $storage, $settings['persistence.'], $fileBaseName);

        $replaceFile = null;

        if (
            get_class($this->event->getRecordOperation()) === CreateRecordOperation::class
            && $storage->hasFileInFolder($fileBaseName, $downloadFolder)
        ) {
            [$fileBaseName, $replaceFile] = $this->handleExistingFile(
                $fileBaseName,
                $downloadFolder
            );
        }

        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        if ($replaceFile) {
            $downloadFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier('0:');
        }

        /** @var File $file */
        [$data, $file] = $this->getFileWithContent($data, $downloadFolder, $fileBaseName);

        if ($replaceFile) {
            $temporaryFile = $file;

            $file = $storage->replaceFile(
                $replaceFile,
                $file->getForLocalProcessing()
            );

            $temporaryFile->delete();
        }

        unset($data['fileData']);
        unset($data['url']);
        unset($data['name']);

        $this->event->getRecordOperation()->setUid($file->getUid());

        $this->event->getRecordOperation()->setData($data);
    }

    /**
     * Creates the file object in FAL.
     *
     * @param Folder $downloadFolder
     * @param string $fileBaseName
     * @return File
     * @throws NotFoundException
     */
    protected function createFileObject(
        Folder $downloadFolder,
        string $fileBaseName
    ): File {
        if (get_class($this->event->getRecordOperation()) === CreateRecordOperation::class) {
            return $downloadFolder->createFile($fileBaseName);
        }

        try {
            $file = $this->resourceFactory->getFileObject(
                $this->mappingRepository->get($this->event->getRecordOperation()->getRemoteId())
            );
        } catch (FileDoesNotExistException $exception) {
            if ($this->mappingRepository->get($this->event->getRecordOperation()->getRemoteId()) === 0) {
                throw new NotFoundException(
                    'The file with remote ID "' . $this->event->getRecordOperation()->getRemoteId()
                    . '" does not exist in this TYPO3 instance.',
                    1634668710602
                );
            }

            throw new NotFoundException(
                'The file with remote ID "' . $this->event->getRecordOperation()->getRemoteId() . '" and UID '
                . '"' . $this->mappingRepository->get($this->event->getRecordOperation()->getRemoteId())
                . '" does not exist.',
                1634668857809
            );
        }

        $this->renameFile($file, $fileBaseName);

        return $file;
    }

    /**
     * Decode base64-encoded file data.
     *
     * @param string $fileData
     * @return string
     */
    protected function handleBase64Input(string $fileData): string
    {
        $stream = fopen('php://temp', 'rw');

        stream_filter_append($stream, 'convert.base64-decode', STREAM_FILTER_WRITE);

        $length = fwrite($stream, $fileData);

        rewind($stream);

        $fileContent = fread($stream, $length);

        fclose($stream);

        return $fileContent;
    }

    /**
     * Handle file data download from a URL.
     *
     * @param string $url
     * @return string|null
     * @throws ClientException
     * @throws NotFoundException
     */
    protected function handleUrlInput(string $url): ?string
    {
        /** @var Client $httpClient */
        $httpClient = GeneralUtility::makeInstance(Client::class);

        $metaData = $this->mappingRepository->getMetaDataValue(
            $this->event->getRecordOperation()->getRemoteId(),
            self::class
        ) ?? [];

        $headers = [];

        if (!empty($metaData['date'])) {
            $headers['If-Modified-Since'] = $metaData['date'];
        }

        if (!empty($metaData['etag'])) {
            $headers['If-None-Match'] = $metaData['etag'];
        }

        try {
            $response = $httpClient->get($url, ['headers' => $headers]);
        } catch (ClientException $exception) {
            if ($exception->getCode() >= 400) {
                throw new NotFoundException(
                    'Request failed. URL: "' . $url . '" Message: "' . $exception->getMessage() . '"',
                    1634667759711
                );
            }

            throw $exception;
        }

        if ($response->getStatusCode() === 304) {
            return null;
        }

        $this->mappingRepository->setMetaDataValue(
            $this->event->getRecordOperation()->getRemoteId(),
            self::class,
            [
                'date' => $response->getHeader('Date'),
                'etag' => $response->getHeader('ETag'),
            ]
        );

        return $response->getBody()->getContents();
    }

    /**
     * Rename a file if the file name has changed.
     *
     * @param File $file
     * @param string $fileName
     * @throws ExistingTargetFileNameException
     */
    protected function renameFile(File $file, string $fileName)
    {
        if ($file->getStorage()->sanitizeFileName($fileName) !== $file->getName()) {
            $file->rename($fileName);
        }
    }

    /**
     * @param string $storagePath
     * @param ResourceStorage $storage
     * @param array $persistenceSettings
     * @param string $fileBaseName
     * @return Folder|\TYPO3\CMS\Core\Resource\InaccessibleFolder|void
     */
    protected function getDownloadFolder(
        string $storagePath,
        ResourceStorage $storage,
        array $persistenceSettings,
        string $fileBaseName
    ) {
        try {
            $downloadFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($storagePath);
        } catch (FolderDoesNotExistException $exception) {
            [, $folderPath] = explode(':', $storagePath);

            $downloadFolder = $storage->createFolder($folderPath);
        }

        $hashedSubfolders = (int)$this->event->getRecordOperation()->getContentObjectRenderer()->stdWrap(
            $persistenceSettings['hashedSubfolders'],
            $persistenceSettings['hashedSubfolders.'] ?? []
        );

        if ($hashedSubfolders > 0) {
            if ($fileBaseName === '') {
                $fileNameHash = bin2hex(random_bytes(18));
            } else {
                $fileNameHash = md5($fileBaseName);
            }

            for ($i = 0; $i < $hashedSubfolders; $i++) {
                $subfolderName = substr($fileNameHash, $i, 1);

                if ($downloadFolder->hasFolder($subfolderName)) {
                    $downloadFolder = $downloadFolder->getSubfolder($subfolderName);

                    continue;
                }

                $downloadFolder = $downloadFolder->createFolder($subfolderName);
            }
        }
        return $downloadFolder;
    }

    /**
     * Retrieves a file from a MediaHelper-compatible URL.
     *
     * @param string $url
     * @param Folder $downloadFolder
     * @param string $fileBaseName
     * @return File|null
     */
    protected function getFileFromMediaUrl(string $url, Folder $downloadFolder, string $fileBaseName): ?File
    {
        $onlineMediaHelperRegistry = GeneralUtility::makeInstance(OnlineMediaHelperRegistry::class);

        $file = $onlineMediaHelperRegistry->transformUrlToFile(
            $url,
            $downloadFolder,
            $onlineMediaHelperRegistry->getSupportedFileExtensions()
        );

        if ($file !== null && $fileBaseName !== '' && $file->exists()) {
            $mediaFileName = $downloadFolder->getStorage()->sanitizeFileName(
                pathinfo($fileBaseName, PATHINFO_FILENAME) . '.' . $file->getExtension()
            );

            $file->rename($mediaFileName);
        }
        return $file;
    }

    /**
     * @param array $data
     * @param Folder $downloadFolder
     * @param string $fileBaseName
     * @return array
     * @throws InvalidFileNameException
     * @throws MissingArgumentException
     */
    protected function getFileWithContent(array $data, Folder $downloadFolder, string $fileBaseName): array
    {
        $file = null;

        if (!empty($data['fileData'])) {
            $fileContent = $this->handleBase64Input($data['fileData']);
        } else {
            if (
                empty($data['url'])
                && get_class($this->event->getRecordOperation()) === CreateRecordOperation::class
            ) {
                throw new MissingArgumentException(
                    'Cannot download file. Missing property "url" in the data.',
                    1634667221986
                );
            }
            if (!empty($data['url'])) {
                $file = $this->getFileFromMediaUrl($data['url'], $downloadFolder, $fileBaseName);

                if ($file === null) {
                    $fileContent = $this->handleUrlInput($data['url']);
                }
            }
        }

        if ($fileBaseName === '' && $file === null) {
            throw new InvalidFileNameException(
                'Empty file name.',
                1643987693168
            );
        }

        if ($file === null) {
            $file = $this->createFileObject($downloadFolder, $fileBaseName);
        }

        if (!empty($fileContent)) {
            $file->setContents($fileContent);
        }

        return[$data, $file];
    }

    /**
     * Returns a unique file name within $folder.
     *
     * @param string $fileName
     * @param Folder $folder
     * @return string
     * @throws \RuntimeException
     */
    protected function getUniqueFileName(string $fileName, Folder $folder): string
    {
        $maxNumber = 99;

        if (!$folder->hasFile($fileName)) {
            return $fileName;
        }

        $fileInfo = PathUtility::pathinfo($fileName);

        $originalExtension = ($fileInfo['extension'] ?? '') ? '.' . $fileInfo['extension'] : '';

        $fileName = $fileInfo['filename'];

        for ($a = 1; $a <= $maxNumber + 1; $a++) {
            if ($a <= $maxNumber) {
                $insert = '_' . sprintf('%02d', $a);
            } else {
                $insert = '_' . substr(md5(StringUtility::getUniqueId()), 0, 6);
            }

            $newFileName = $fileName . $insert . $originalExtension;

            if (!$folder->getStorage()->hasFileInFolder($newFileName, $folder)) {
                return $newFileName;
            }
        }

        throw new \RuntimeException(
            'Last possible name "' . $newFileName . '" is already taken.',
            1649841992746
        );
    }

    /**
     * @param string $fileBaseName
     * @param Folder $downloadFolder
     * @return array
     * @throws IdentityConflictException
     */
    protected function handleExistingFile(string $fileBaseName, Folder $downloadFolder): array
    {
        $handleExistingFile = GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('interest', 'handleExistingFile') ?? DuplicationBehavior::CANCEL;

        if ($handleExistingFile === DuplicationBehavior::CANCEL) {
            throw new IdentityConflictException(
                'File "' . $fileBaseName . '" already exists in "' . $downloadFolder->getReadablePath() . '".',
                1634666560886
            );
        }

        if ($handleExistingFile === DuplicationBehavior::RENAME) {
            $fileBaseName = $this->getUniqueFileName($fileBaseName, $downloadFolder);
        }

        $replaceFile = null;

        if ($handleExistingFile === DuplicationBehavior::REPLACE) {
            $replaceFile = $downloadFolder->getStorage()->getFileInFolder($fileBaseName, $downloadFolder);
        }

        return [$fileBaseName, $replaceFile];
    }
}
