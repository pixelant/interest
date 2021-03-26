<?php

namespace Pixelant\Interest\Handler;

use GuzzleHttp\Client;
use Pixelant\Interest\Handler\Exception\FileHandlingException;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileUploadHandler implements HandlerInterface
{

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * FileUploadHandler constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function uploadFile(InterestRequestInterface $request): ResponseInterface
    {
        $data = $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();
        $fileDenyValidator = $this->objectManager->get(FileNameValidator::class);

        // Check for unacceptable file formats.
        if (!$fileDenyValidator->isValid($data['data']['name'])){
            return $responseFactory->createErrorResponse(
                ['error' => 'Unable to load file with this format'],
                403,
                $request
            );
        }

        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        $httpClient = $this->objectManager->get(Client::class);
        $fileBaseName = basename($data['data']['url']);
        $storagePath = $configuration['persistence']['fileUploadFolderPath'];
        list($storageId,$subFolderPath) = explode(':', $storagePath);
        $storage = $this->objectManager->get(StorageRepository::class)->findByUid((int)$storageId);

        if ($data['data']['fileData']){
            $fileBaseName = $data['data']['name'];
            $stream = fopen('fileadmin/'.$fileBaseName,'w');
            stream_filter_append($stream, 'convert.base64-decode',STREAM_FILTER_WRITE);
            fwrite($stream, $data['data']['fileData']);
            fclose($stream);
        } else {
            $response = $httpClient->get($data['data']['url']);
            GeneralUtility::writeFile('fileadmin/'.$fileBaseName, $response->getBody());
        }

        $file = $storage->addFile(
            'fileadmin/'.$fileBaseName,
            $storage->getFolder($subFolderPath),
            $fileBaseName
        );

        if ($file){
            return $responseFactory->createSuccessResponse(
                ['status' => 'success'],
                200,
                $request
            );
        } else {
            throw new FileHandlingException(
                'File was not uploaded.',
                $request
            );
        }
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

    public function configureRoutes(RouterInterface $router, InterestRequestInterface $request)
    {
        $resourceType = $request->getResourceType()->__toString();
        $router->add(Route::post($resourceType, [$this, 'uploadFile']));
    }
}
