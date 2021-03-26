<?php

namespace Pixelant\Interest\Handler;

use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;

class BatchHandler extends CrudHandler
{
    /**
     * @param InterestRequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function batchCreate(InterestRequestInterface $request): ResponseInterface
    {
        $importDataArray = $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();
        $standardizedArray = $this->getStandardizedArray($importDataArray);
        $responseData = [
            'status' => 'success',
        ];
        $isSuccess = true;

        foreach ($standardizedArray as $tableName => $importData) {
            foreach ($importData as $importItem) {
                $response = $this->createRecord($request, $importItem, $tableName);

                // Set stream pointer to the beginning of the stream.
                $response->getBody()->rewind();

                $responseData['statuses'][$importItem['remoteId']] = [
                    $this->createArrayFromJson($response->getBody()->getContents()),
                ];

                if ($response->getStatusCode() !== 200) {
                    $isSuccess = false;
                }
            }
        }

        if (!$isSuccess) {
            $responseData['status'] = 'failure';
        }

        return $responseFactory->createSuccessResponse($responseData, 200, $request);
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Core\Exception
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function batchUpdate(InterestRequestInterface $request): ResponseInterface
    {
        $updateDataArray = $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();
        $standardizedArray = $this->getStandardizedArray($updateDataArray);
        $responseData = [
            'status' => 'success',
        ];
        $isSuccess = true;

        foreach ($standardizedArray as $tableName => $importData) {
            foreach ($importData as $importItem) {
                $response = $this->updateRecord($request, $importItem, $tableName);

                // Set stream pointer to the beginning of the stream.
                $response->getBody()->rewind();

                $responseData['statuses'][$importItem['remoteId']] = [
                    $this->createArrayFromJson($response->getBody()->getContents()),
                ];

                if ($response->getStatusCode() !== 200) {
                    $isSuccess = false;
                }
            }
        }

        if (!$isSuccess) {
            $responseData['status'] = 'failure';
        }

        return $responseFactory->createSuccessResponse($responseData, 200, $request);
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function batchCreateOrUpdate(InterestRequestInterface $request): ResponseInterface
    {
        $importDataArray = $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();
        $standardizedArray = $this->getStandardizedArray($importDataArray);
        $responseData = [
            'status' => 'success',
        ];
        $isSuccess = true;

        foreach ($standardizedArray as $tableName => $importData) {
            foreach ($importData as $importItem) {
                if ($this->checkIfRelationExists($importItem['remoteId'])) {
                    $response = $this->updateRecord($request, $importItem, $tableName);
                } else {
                    $response = $this->createRecord($request, $importItem, $tableName);
                }

                // Set stream pointer to the beginning of the stream.
                $response->getBody()->rewind();

                $responseData['statuses'][$importItem['remoteId']] = [
                    $this->createArrayFromJson($response->getBody()->getContents()),
                ];

                if ($response->getStatusCode() !== 200) {
                    $isSuccess = false;
                }
            }
        }

        if (!$isSuccess) {
            $responseData['status'] = 'failure';
        }

        return $responseFactory->createSuccessResponse($responseData, 200, $request);
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function batchDelete(InterestRequestInterface $request): ResponseInterface
    {
        $deleteDataArray = $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();
        $responseData = [
            'status' => 'success',
        ];
        $isSuccess = true;

        foreach ($deleteDataArray['data'] as $tableName => $deleteData) {
            foreach ($deleteData as $remoteId) {
                $response = $this->deleteRecord($request, ['remoteId' => $remoteId], $tableName);

                $response->getBody()->rewind();

                $responseData['statuses'][$remoteId] = [
                    $this->createArrayFromJson($response->getBody()->getContents()),
                ];

                if ($response->getStatusCode() !== 200) {
                    $isSuccess = false;
                }
            }
        }

        if (!$isSuccess) {
            $responseData['status'] = 'failure';
        }

        return $responseFactory->createSuccessResponse($responseData, 200, $request);
    }

    /**
     * Convert data from request to standardized array for CRUD actions.
     *
     * @param array $array
     * @return array
     */
    private function getStandardizedArray(array $array): array
    {
        $standardizedArray = [];

        foreach ($array['data'] as $tableName => $data) {
            foreach ($data as $remoteId => $importData) {
                $standardizedArray[$tableName][] = [
                    'remoteId' => $remoteId,
                    'data' => $importData,
                ];
            }
        }

        return $standardizedArray;
    }

    public function configureRoutes(RouterInterface $router, InterestRequestInterface $request): void
    {
        $resourceType = $request->getResourceType()->__toString();
        $router->add(Route::post($resourceType, [$this, 'batchCreate']));
        $router->add(Route::patch($resourceType, [$this, 'batchUpdate']));
        $router->add(Route::put($resourceType, [$this, 'batchCreateOrUpdate']));
        $router->add(Route::delete($resourceType, [$this, 'batchDelete']));
    }
}
