<?php

namespace Pixelant\Interest\Handler;

use GuzzleHttp\Client;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Handler\Exception\FileHandlingException;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class FileUploadHandler implements HandlerInterface
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
     * @param RemoteIdMappingRepository $mappingRepository
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        DataHandler $dataHandler,
        RemoteIdMappingRepository $mappingRepository,
        ResourceFactory $resourceFactory
    ) {
        $this->objectManager = $objectManager;
        $this->dataHandler = $dataHandler;
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
            if ($data['data']['overwriteFlag'] === 'use') {
                $file = $storage->getFileInFolder($fileBaseName, $downloadFolder);

                $this->mappingRepository->add(
                    $data['remoteId'],
                    self::FILES_TABLE,
                    $file->getUid()
                );

                return $responseFactory->createSuccessResponse(
                    ['status' => 'success'],
                    200,
                    $request
                );
            }

            return $responseFactory->createErrorResponse(
                ['status' => 'error', 'message' => 'File already exists, no overwrite access given.'],
                400,
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

        return $responseFactory->createSuccessResponse(
            ['status' => 'success'],
            200,
            $request
        );
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

        if ($storage->hasFileInFolder($data['data']['name'], $downloadFolder)) {
            $file = $storage->getFileInFolder($data['data']['name'], $downloadFolder);
        } else {
            return $responseFactory->createErrorResponse(
                ['status' => 'Not exists', 'message' => 'Given file are not exists.'],
                201,
                $request
            );
        }

        if ($this->mappingRepository->exists($data['data']['productRemoteId'])) {
            $productId = $this->mappingRepository->get($data['data']['productRemoteId']);
        }

        $placeholderId = StringUtility::getUniqueId('NEW');

        $referenceData[self::REFERENCE_TABLE][$placeholderId] = [
            'table_local' => 'sys_file',
            'uid_local' => $file->getUid(),
            'tablenames' => self::PRODUCT_TABLE,
            'uid_foreign' => $productId,
            'fieldname' => 'images',
            'pid' => (int)$configuration['persistence']['storagePid'],
        ];

        $referenceData[self::PRODUCT_TABLE][$productId] = [
            'images' => $placeholderId,
        ];

        $this->dataHandler->start($referenceData, []);
        $this->dataHandler->process_datamap();

        if (count($this->dataHandler->errorLog) === 0) {
            return $responseFactory->createSuccessResponse(
                ['status' => 'success'],
                200,
                $request
            );
        }

        return $responseFactory->createErrorResponse(
            ['status' => 'failure', 'message' => 'Error occured during data handling process'],
            400,
            $request
        );
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function getProductImages(InterestRequestInterface $request): ResponseInterface
    {
        $responseFactory = $this->objectManager->getResponseFactory();
        $serverParams = $request->getServerParams();
        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        $storagePath = $configuration['persistence']['fileUploadFolderPath'];
        $storage = $this->resourceFactory->getStorageObjectFromCombinedIdentifier($storagePath);
        $downloadFolder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($storagePath);
        $files = $storage->getFilesInFolder($downloadFolder);

        $data = [];
        if (count($files) > 0) {
            foreach ($files as $file) {
                $url = $serverParams['REQUEST_SCHEME'] . '://' . $serverParams['HTTP_HOST'] . '/' . $file->getPublicUrl();

                $data[] = [
                    'remoteId' => $file->getName(),
                    'data' => [
                        'url' => $url,
                        'name' => $file->getName(),
                        'overwriteFlag' => 'use',
                    ],
                ];
            }
        }

        return $responseFactory->createSuccessResponse($data, 200, $request);
    }

    /**
     * @param string $json
     * @return array
     */
    private function createArrayFromJson(string $json): array
    {
        $stdClass = json_decode($json);

        return json_decode(json_encode($stdClass), true);
    }

    public function configureRoutes(RouterInterface $router, InterestRequestInterface $request): void
    {
        $resourceType = $request->getResourceType()->__toString();
        $router->add(Route::post($resourceType, [$this, 'uploadFile']));
        $router->add(Route::post($resourceType . '/createReference', [$this, 'createFileReference']));
        $router->add(Route::get($resourceType, [$this, 'getProductImages']));
    }
}
