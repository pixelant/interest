<?php

namespace Pixelant\Interest\Handler;

use GuzzleHttp\Client;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Handler\Exception\FileHandlingException;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

class FileUploadHandler extends CrudHandler
{
    public const PRODUCT_TABLE = 'tx_pxaproductmanager_domain_model_product';

    public const REFERENCE_TABLE = 'sys_file_reference';

    public const FILES_TABLE = 'sys_file';

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var RemoteIdMappingRepository
     */
    protected RemoteIdMappingRepository $mappingRepository;

    /**
     * @var PendingRelationsRepository
     */
    protected PendingRelationsRepository $pendingRelationsRepository;

    /**
     * @var EventDispatcher
     */
    protected EventDispatcher $eventDispatcher;

    /**
     * @var DataHandler
     */
    protected DataHandler $dataHandler;

    /**
     * @var ResourceFactory
     */
    protected ResourceFactory $resourceFactory;

    /**
     * FileUploadHandler constructor.
     * @param ObjectManagerInterface $objectManager
     * @param DataHandler $dataHandler
     * @param PendingRelationsRepository $pendingRelationsRepository
     * @param RemoteIdMappingRepository $mappingRepository ,
     * @param EventDispatcher $eventDispatcher
     * @param ResourceFactory $resourceFactory
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        DataHandler $dataHandler,
        PendingRelationsRepository $pendingRelationsRepository,
        RemoteIdMappingRepository $mappingRepository,
        EventDispatcher $eventDispatcher,
        ResourceFactory $resourceFactory
    ) {
        parent::__construct($objectManager, $dataHandler, $mappingRepository, $pendingRelationsRepository, $eventDispatcher);
        $this->objectManager = $objectManager;
        $this->pendingRelationsRepository = $pendingRelationsRepository;
        $this->mappingRepository = $mappingRepository;
        $this->resourceFactory = $resourceFactory;
    }

    /**
     * @param InterestRequestInterface $request
     * @throws FileHandlingException
     * @return ResponseInterface
     */
    public function uploadFile(InterestRequestInterface $request): ResponseInterface
    {
        $data = $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();
        $fileDenyValidator = $this->objectManager->get(FileNameValidator::class);
        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        $storagePath = $configuration['persistence']['fileUploadFolderPath'];
        $downloadFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($storagePath);
        $storage = $this->resourceFactory->getStorageObjectFromCombinedIdentifier($storagePath);

        // Check for unacceptable file formats.
        if (!$fileDenyValidator->isValid($data['data']['name'])) {
            return $responseFactory->createErrorResponse(
                ['error' => 'Unable to load file with this format'],
                403,
                $request
            );
        }

        $fileBaseName = $data['data']['name'];

        if ($storage->hasFileInFolder($fileBaseName, $downloadFolder)) {
            throw new FileHandlingException(
                'File already exists.',
                $request
            );
        }
        $file = $downloadFolder->createFile($fileBaseName);

        if ($data['data']['fileData']) {
            $stream = fopen('php://temp', 'rw');
            stream_filter_append($stream, 'convert.base64-decode', STREAM_FILTER_WRITE);
            $length = fwrite($stream, $data['data']['fileData']);
            rewind($stream);
            $file->setContents(fread($stream, $length));
            fclose($stream);
        } else {
            $httpClient = $this->objectManager->get(Client::class);
            $response = $httpClient->get($data['data']['url']);

            if ($response->getStatusCode() >= 400) {
                throw new FileHandlingException(
                    'Request to given url failed. Reason phrase:' . $response->getReasonPhrase(),
                    $request
                );
            }

            $file->setContents($response->getBody()->getContents());
        }

        if (!$this->mappingRepository->exists($data['remoteId'])) {
            $this->mappingRepository->add(
                $data['remoteId'],
                self::FILES_TABLE,
                $file->getUid()
            );
        }

        return $responseFactory->createSuccessResponse(
            ['status' => 'success', 'uid' => $file->getUid()],
            200,
            $request
        );
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function updateFile(InterestRequestInterface $request): ResponseInterface
    {
        $data = $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();
        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        $storagePath = $configuration['persistence']['fileUploadFolderPath'];
        $downloadFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($storagePath);
        $storage = $this->resourceFactory->getStorageObjectFromCombinedIdentifier($storagePath);
        $fileBaseName = $data['data']['name'];

        if ($storage->hasFileInFolder($fileBaseName, $downloadFolder)) {
            $file = $storage->getFileInFolder($fileBaseName, $downloadFolder);
        } else {
            throw new FileHandlingException(
                'File are not exists.',
                $request
            );
        }

        if ($data['data']['fileData']) {
            $stream = fopen('php://temp', 'rw');
            stream_filter_append($stream, 'convert.base64-decode', STREAM_FILTER_WRITE);
            $length = fwrite($stream, $data['data']['fileData']);
            rewind($stream);
            $file->setContents(fread($stream, $length));
            fclose($stream);
        } else {
            $httpClient = $this->objectManager->get(Client::class);
            $response = $httpClient->get($data['data']['url']);

            if ($response->getStatusCode() >= 400) {
                throw new FileHandlingException(
                    'Request to given url failed. Reason phrase:' . $response->getReasonPhrase(),
                    $request
                );
            }

            $file->setContents($response->getBody()->getContents());
        }

        if (!$this->mappingRepository->exists($data['remoteId'])) {
            $this->mappingRepository->add(
                $data['remoteId'],
                self::FILES_TABLE,
                $file->getUid()
            );
        }

        return $responseFactory->createSuccessResponse(
            ['status' => 'success', 'uid' => $file->getUid()],
            200,
            $request
        );
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function uploadOrUpdateFile(InterestRequestInterface $request): ResponseInterface
    {
        $data = $this->createArrayFromJson($request->getBody()->getContents());
        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        $storagePath = $configuration['persistence']['fileUploadFolderPath'];
        $downloadFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($storagePath);
        $storage = $this->resourceFactory->getStorageObjectFromCombinedIdentifier($storagePath);
        $fileBaseName = $data['data']['name'];

        // Seek to the beginning of the stream.
        $request->getBody()->rewind();

        if ($storage->hasFileInFolder($fileBaseName, $downloadFolder)) {
            return $this->updateFile($request);
        }

        return $this->uploadFile($request);
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function createFileReference(InterestRequestInterface $request): ResponseInterface
    {
        // TODO: This function is too Product-Manager oriented.
        // TODO: Must be rewritten: https://github.com/pixelant/interest/pull/29#discussion_r615805790

        $data = $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();
        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        $storagePath = $configuration['persistence']['fileUploadFolderPath'];
        $storage = $this->resourceFactory->getStorageObjectFromCombinedIdentifier($storagePath);
        $downloadFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($storagePath);
        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        ExtensionManagementUtility::allowTableOnStandardPages(self::REFERENCE_TABLE);
        $productId = null;

        // Seek to the beginning of the stream.
        $request->getBody()->rewind();

        if ($storage->hasFileInFolder($data['data']['name'], $downloadFolder)) {
            $file = $storage->getFileInFolder($data['data']['name'], $downloadFolder);
            $fileRemoteId = $this->mappingRepository->getRemoteId(self::FILES_TABLE, $file->getUid());

            if ($fileRemoteId === false) {
                $this->mappingRepository->add(
                    $file->getName(),
                    self::FILES_TABLE,
                    $file->getUid()
                );

                $fileRemoteId = $this->mappingRepository->getRemoteId(self::FILES_TABLE, $file->getUid());
            }
        } else {
            return $responseFactory->createErrorResponse(
                ['status' => 'Not exists', 'message' => 'Given file are not exists.'],
                201,
                $request
            );
        }

        $data = [
            'remoteId' => $data['remoteId'],
            'data' => [
                'table_local' => self::FILES_TABLE,
                'uid_local' => [$fileRemoteId],
                'tablenames' => self::PRODUCT_TABLE,
                'uid_foreign' => [$data['data']['productRemoteId']],
                'fieldname' => 'images',
                'storage' => $data['data']['storage'],
            ],
        ];

        if ($this->mappingRepository->exists($data['remoteId'])) {
            $referenceResponse = $this->updateRecord($request, $data, self::REFERENCE_TABLE);
        } else {
            $referenceResponse = $this->createRecord($request, false, $data, self::REFERENCE_TABLE);
        }

        if ($referenceResponse->getStatusCode() === 200) {
            $data = [
                'remoteId' => $data['data']['uid_foreign'][0][0],
                'data' => [
                    'images' => [$data['remoteId']],
                    'storage' => $data['data']['storage'],
                ],
            ];

            return $this->updateRecord($request, $data, self::PRODUCT_TABLE);
        }

        throw new FileHandlingException(
            'Error occured during reference creating process,',
            $request
        );
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException
     */
    public function getFilesFromStorage(InterestRequestInterface $request): ResponseInterface
    {
        $responseFactory = $this->objectManager->getResponseFactory();
        $serverParams = $request->getServerParams();
        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        $storagePath = $configuration['persistence']['fileUploadFolderPath'];
        $storage = $this->resourceFactory->getStorageObjectFromCombinedIdentifier($storagePath);
        $downloadFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($storagePath);
        $files = $storage->getFilesInFolder($downloadFolder, 0, 0, true, true);

        $data = [];
        if (count($files) > 0) {
            foreach ($files as $file) {
                $url = $serverParams['REQUEST_SCHEME'] . '://' . $serverParams['HTTP_HOST'] . '/' . $file->getPublicUrl();
                $remoteId = $this->mappingRepository->getRemoteId(self::FILES_TABLE, $file->getUid());

                $data[] = [
                    'remoteId' => (!$remoteId) ? $file->getName() : $remoteId,
                    'url' => $url,
                    'name' => $file->getName(),
                ];
            }
        }

        return $responseFactory->createSuccessResponse($data, 200, $request);
    }

    public function configureRoutes(RouterInterface $router, InterestRequestInterface $request): void
    {
        $resourceType = $request->getResourceType()->__toString();
        $router->add(Route::post($resourceType, [$this, 'uploadFile']));
        $router->add(Route::put($resourceType . '/createReference', [$this, 'createFileReference']));
        $router->add(Route::get($resourceType, [$this, 'getFilesFromStorage']));
        $router->add(Route::patch($resourceType, [$this, 'updateFile']));
        $router->add(Route::put($resourceType, [$this, 'uploadOrUpdateFile']));
    }
}
