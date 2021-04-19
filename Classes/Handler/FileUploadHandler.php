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
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class FileUploadHandler implements HandlerInterface
{
    public const PRODUCT_TABLE = 'tx_pxaproductmanager_domain_model_product';

    public const REFERENCE_TABLE = 'sys_file_reference';

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
     * FileUploadHandler constructor.
     * @param ObjectManagerInterface $objectManager
     * @param RemoteIdMappingRepository $mappingRepository
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        DataHandler $dataHandler,
        RemoteIdMappingRepository $mappingRepository
    ) {
        $this->objectManager = $objectManager;
        $this->dataHandler = $dataHandler;
        $this->mappingRepository = $mappingRepository;
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
        [$storageId, $subFolderPath] = explode(':', $storagePath);
        $storage = $this->objectManager->get(StorageRepository::class)->findByUid((int)$storageId);
        $imageFolder = $storage->getFolder($subFolderPath);

        if ($storage->hasFileInFolder($data['data']['name'], $imageFolder)) {
            if ($this->createFileReference($data, $storage->getFileInFolder($data['data']['name'], $imageFolder))) {
                return $responseFactory->createSuccessResponse(
                    ['status' => 'success'],
                    200,
                    $request
                );
            }

            return $responseFactory->createErrorResponse(
                ['status' => 'failure', 'message' => 'Error occured during creating file reference process'],
                400,
                $request
            );
        }

        // Check for unacceptable file formats.
        if (!$fileDenyValidator->isValid($data['data']['name'])) {
            return $responseFactory->createErrorResponse(
                ['error' => 'Unable to load file with this format'],
                403,
                $request
            );
        }

        $httpClient = $this->objectManager->get(Client::class);
        $fileBaseName = basename($data['data']['url']);

        if ($data['data']['fileData']) {
            $fileBaseName = $data['data']['name'];
            $stream = fopen('fileadmin/' . $fileBaseName, 'w');
            stream_filter_append($stream, 'convert.base64-decode', STREAM_FILTER_WRITE);
            fwrite($stream, $data['data']['fileData']);
            fclose($stream);
        } else {
            $response = $httpClient->get($data['data']['url']);
            GeneralUtility::writeFile('fileadmin/' . $fileBaseName, $response->getBody());
        }

        $file = $storage->addFile(
            'fileadmin/' . $fileBaseName,
            $storage->getFolder($subFolderPath),
            $fileBaseName
        );

        if ($file) {
            if ($this->createFileReference($data, $file)) {
                return $responseFactory->createSuccessResponse(
                    ['status' => 'success'],
                    200,
                    $request
                );
            }

            return $responseFactory->createErrorResponse(
                ['status' => 'failure', 'message' => 'Error occured during creating file reference process'],
                400,
                $request
            );
        }

        throw new FileHandlingException(
            'File was not uploaded.',
            $request
        );
    }

    /**
     * @param array $data
     * @param File $file
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function createFileReference(array $data, File $file): bool
    {
        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        ExtensionManagementUtility::allowTableOnStandardPages(self::REFERENCE_TABLE);
        $productId = null;

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
            return true;
        }

        return false;
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
    }
}
