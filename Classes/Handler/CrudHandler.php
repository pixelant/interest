<?php

declare(strict_types=1);

namespace Pixelant\Interest\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Exception\AbstractException;
use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Handler\Exception\NotFoundException;
use Pixelant\Interest\Handler\ExceptionConverter\OperationToRequestHandlerExceptionConverter;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Pixelant\Interest\DataHandling\DataHandler;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentValueException;

class CrudHandler implements HandlerInterface
{
    public const REMOTE_ID_MAPPING_TABLE = 'tx_interest_remote_id_mapping';

    public const PENDING_RELATIONS_TABLE = 'tx_interest_pending_relations';

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var InterestRequestInterface
     */
    protected RequestInterface $currentRequest;

    /**
     * CrudHandler constructor.
     * @param ObjectManagerInterface $objectManager
     * @param DataHandler $dataHandler
     * @param RemoteIdMappingRepository $mappingRepository
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    public function setCurrentRequest(InterestRequestInterface $request): void
    {
        $this->currentRequest = $request;
    }

    /**
     * @param InterestRequestInterface $request
     * @param bool $isUpdate
     * @return ResponseInterface
     */
    public function createRecord(
        InterestRequestInterface $request,
        bool $isUpdate = false,
    ): ResponseInterface {
        $request->getBody()->rewind();

        $this->setCurrentRequest($request);

        [
            'remoteId' => $remoteId,
            'data' => $data,
            'language' => $language,
            'metaData' => $metaData,
            'workspace' => $workspace,
        ] = $this->createArrayFromJson($request->getBody()->getContents());

        $responseFactory = $this->objectManager->getResponseFactory();

        $tableName = (!empty($tableName)) ? $tableName : $request->getResourceType()->__toString();

        if ($remoteId === null) {
            throw new NotFoundException(
                'No remote ID given.',
                $request
            );
        }

        try {
            try {
                new CreateRecordOperation($data, $tableName, $remoteId, $language, $workspace, $metaData);
            } catch (IdentityConflictException $exception) {
                if (!$isUpdate) {
                    throw $exception;
                }

                new UpdateRecordOperation($data, $tableName, $remoteId, $language, $workspace, $metaData);
            }
        } catch (AbstractException $operationException) {
            throw OperationToRequestHandlerExceptionConverter::convert($operationException, $request);
        }

        return $responseFactory->createSuccessResponse(
            [
                'status' => 'success',
                'data' => [
                    'uid' => $this->mappingRepository->get($remoteId),
                ],
            ],
            200,
            $request
        );
    }

    /**
     * @param string $json
     * @return array
     */
    protected function createArrayFromJson(string $json): array
    {
        $stdClass = json_decode($json);

        return json_decode(json_encode($stdClass), true);
    }

    /**
     * @param InterestRequestInterface $request
     * @param array|null $importData
     * @return ResponseInterface
     * @throws InvalidArgumentValueException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function updateRecord(
        InterestRequestInterface $request,
        array $importData = null,
        string $tableName = ''
    ): ResponseInterface {
        return $this->createRecord($request, true, $importData, $tableName);
    }

    /**
     * @param InterestRequestInterface $request
     * @param bool $isUpdate
     * @return ResponseInterface
     * @throws InvalidArgumentValueException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function createOrUpdateRecord(InterestRequestInterface $request): ResponseInterface
    {
        $request->getBody()->rewind();
        $recordData = $this->createArrayFromJson($request->getBody()->getContents());

        if (!$this->mappingRepository->exists($recordData['remoteId'])) {
            return $this->createRecord($request);
        }

        return $this->updateRecord($request);
    }

    /**
     * @param InterestRequestInterface $request
     * @param array $data
     * @return ResponseInterface
     */
    public function deleteRecord(
        InterestRequestInterface $request,
        array $data = []
    ): ResponseInterface {
        $this->setCurrentRequest($request);

        [
            'remoteId' => $remoteId,
            'language' => $language,
            'workspace' => $workspace,
        ] = $data ?? $this->createArrayFromJson($request->getBody()->getContents());

        try {
            new DeleteRecordOperation($remoteId, $language, $workspace);
        } catch (AbstractException $operationException) {
            throw OperationToRequestHandlerExceptionConverter::convert($operationException, $request);
        }

        return $this
            ->objectManager
            ->getResponseFactory()
            ->createSuccessResponse(['status' => 'success'], 200, $request);
    }

    /**
     * @param RouterInterface $router
     * @param InterestRequestInterface $request
     */
    public function configureRoutes(RouterInterface $router, InterestRequestInterface $request): void
    {
        $resourceType = $request->getResourceType()->__toString();
        $router->add(Route::post($resourceType, [$this, 'createRecord']));
        $router->add(Route::patch($resourceType, [$this, 'updateRecord']));
        $router->add(Route::put($resourceType, [$this, 'createOrUpdateRecord']));
        $router->add(Route::delete($resourceType, [$this, 'deleteRecord']));
        $router->add(Route::get($resourceType, [$this, 'readRecords']));
    }
}
