<?php

declare(strict_types=1);

namespace Pixelant\Interest\Handler;

use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Handler\Exception\ConflictException;
use Pixelant\Interest\Handler\Exception\DataHandlerErrorException;
use Pixelant\Interest\Handler\Exception\NotFoundException;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class CrudHandler implements HandlerInterface
{
    public const REMOTE_ID_MAPPING_TABLE = 'tx_interest_remote_id_mapping';

    public const PENDING_RELATIONS_TABLE = 'tx_interest_pending_relations';

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var DataHandler
     */
    protected DataHandler $dataHandler;

    /**
     * @var RemoteIdMappingRepository
     */
    protected RemoteIdMappingRepository $mappingRepository;

    /**
     * @var PendingRelationsRepository
     */
    protected PendingRelationsRepository $pendingRelationsRepository;

    /**
     * @var InterestRequestInterface
     */
    protected RequestInterface $currentRequest;

    /**
     * Used by prepareRelations() and persistPendingRelations().
     *
     * $pendingRelations[<remoteId>][<fieldName>] = [<remoteId1>, <remoteId2>, ...]
     *
     * @var array
     */
    protected array $pendingRelations = [];

    /**
     * Cache for results from $this->getTypeValue(). Key is `<table>_<foreignId>`.
     *
     * @var array
     */
    private array $getTypeValueCache = [];

    /**
     * CrudHandler constructor.
     * @param ObjectManagerInterface $objectManager
     * @param DataHandler $dataHandler
     * @param RemoteIdMappingRepository $mappingRepository
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        DataHandler $dataHandler,
        RemoteIdMappingRepository $mappingRepository,
        PendingRelationsRepository $pendingRelationsRepository
    ) {
        $this->objectManager = $objectManager;
        $this->dataHandler = $dataHandler;
        $this->mappingRepository = $mappingRepository;
        $this->pendingRelationsRepository = $pendingRelationsRepository;
    }

    public function setCurrentRequest(InterestRequestInterface $request): void
    {
        $this->currentRequest = $request;
    }

    /**
     * @param InterestRequestInterface $request
     * @param array $recordData
     * @param string|null $tableName
     * @return ResponseInterface
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function createRecord(InterestRequestInterface $request, array $recordData = [], ?string $tableName = null)
    {
        $this->setCurrentRequest($request);

        [
            'remoteId' => $remoteId,
            'data' => $importData
        ] = !empty($recordData) ? $recordData : $this->createArrayFromJson($request->getBody()->getContents());

        $responseFactory = $this->objectManager->getResponseFactory();
        $tableName = $tableName ?? $request->getResourceType()->__toString();

        if ($remoteId === null) {
            throw new NotFoundException(
                'No remote ID given.',
                $request
            );
        }

        if ($this->mappingRepository->exists($remoteId)) {
            throw new ConflictException(
                'Remote ID "' . $remoteId . '" already exists.',
                $request
            );
        }

        $fieldsNotInTca = array_diff_key($importData, $GLOBALS['TCA'][$tableName]['columns']);
        if (count($fieldsNotInTca) > 0) {
            throw new ConflictException(
                'Unknown field(s) in field list: ' . implode(', ', array_keys($fieldsNotInTca)),
                $request
            );
        }

        $placeholderId = StringUtility::getUniqueId('NEW');

        $localId = $this->executeDataInsertOrUpdate($tableName, $placeholderId, $remoteId, $importData);

        return $responseFactory->createSuccessResponse(
            [
                'status' => 'success',
                'data' => [
                    'uid' => $localId,
                ],
            ],
            200,
            $request
        );
    }

    /**
     * Execute a data update/insert operation.
     *
     * @param string $tableName
     * @param string $localId
     * @param string $remoteId
     * @param array $recordData
     * @throws DataHandlerErrorException
     * @return int The inserted UID
     */
    protected function executeDataInsertOrUpdate(
        string $tableName,
        string $localId,
        string $remoteId,
        array $recordData
    ): int {
        $data = [];

        $isNewRecord = strpos($localId, 'NEW') === 0;

        ExtensionManagementUtility::allowTableOnStandardPages($tableName);

        $recordData = $this->prepareRelations($tableName, $remoteId, $recordData);

        if (!$recordData['pid']) {
            // TODO: Use UserTS for this.

            $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
            $recordData['pid'] = $configuration['persistence']['storagePid'];
        }

        $data[$tableName][$localId] = $recordData;

        if ($isNewRecord) {
            $this->resolvePendingRelations($tableName, $remoteId, $localId, $data);
        }

        if (!$this->dataHandling($data)) {
            throw new DataHandlerErrorException($this->dataHandler, $this->currentRequest);
        }

        if ($isNewRecord) {
            $this->mappingRepository->add(
                $remoteId,
                $tableName,
                $this->dataHandler->substNEWwithIDs[$localId]
            );

            $this->pendingRelationsRepository->removeRemote($remoteId);
        }

        $this->persistPendingRelations();

        if ($isNewRecord) {
            return (int)$this->dataHandler->substNEWwithIDs[$localId];
        }

        return (int)$localId;
    }

    /**
     * Finds pending relations for a $remoteId record that is being inserted into the database and adds DataHandler
     * datamap array inserting any pending relations into the database as well.
     *
     * @param string $table The table $remoteId is being inserted into.
     * @param string $remoteId The remote ID
     * @param array $data DataHandler datamap array to insert data into. Passed by reference.
     */
    protected function resolvePendingRelations(string $table, string $remoteId, string $placeholderId, &$data): void
    {
        foreach ($this->pendingRelationsRepository->get($remoteId) as $pendingRelation) {
            /** @var RelationHandler $relationHandler */
            $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

            $relationHandler->start(
                '',
                $table,
                '',
                $pendingRelation['record_uid'],
                $pendingRelation['table'],
                $this->getTcaFieldConfigurationAndRespectColumnsOverrides(
                    $pendingRelation['table'],
                    $pendingRelation['field'],
                    BackendUtility::getRecord(
                        $pendingRelation['table'],
                        $pendingRelation['record_uid']
                    )
                )
            );

            $existingRelations = array_column(
                $relationHandler->getFromDB()[$pendingRelation['table']] ?? [],
                'uid'
            );

            $data[$pendingRelation['table']][$pendingRelation['record_uid']][$pendingRelation['field']]
                = implode(',', array_merge($existingRelations, [$placeholderId]));
        }
    }

    /**
     * Prepare relations and return a modified version of $importData.
     *
     * You must call persistPendingRelations() after processing $importData with DataHandler. All relations to records
     * are either changed from the remote ID to the correct localID or marked as a pending relation. Pending relation
     * information is temporarily added to $this->pendingRelations and persisted using persistPendingRelations().
     *
     * @see persistPendingRelations()
     * @param string $tableName
     * @param string $remoteId
     * @param array $importData Referenced array of import data (record fieldName => value pairs).
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function prepareRelations(string $tableName, string $remoteId, array $importData): array
    {
        foreach ($importData as $fieldName => $fieldValue) {
            if ($this->isRelationField($tableName, $fieldName, $remoteId, $importData)) {
                if (!is_array($fieldValue)) {
                    $fieldValue = GeneralUtility::trimExplode(',', $fieldValue, true);
                }

                $importData[$fieldName] = [];
                foreach ($fieldValue as $remoteIdRelation) {
                    if ($this->mappingRepository->exists($remoteIdRelation)) {
                        $importData[$fieldName][] = $this->mappingRepository->get($remoteIdRelation);

                        continue;
                    }

                    $this->pendingRelations[$remoteId][$fieldName][] = $remoteIdRelation;
                }
            }

            $tcaConfiguration = $GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config'];

            if ($tcaConfiguration['type'] === 'inline') {
                $importData[$fieldName] = CsvUtility::csvValues($importData[$fieldName], ',', '');
            }
        }

        return $importData;
    }

    /**
     * Persists information about pending relations to the database.
     *
     * @see prepareRelations()
     */
    protected function persistPendingRelations(): void
    {
        foreach ($this->pendingRelations as $remoteId => $data) {
            foreach ($data as $fieldName => $relations) {
                $this->pendingRelationsRepository->set(
                    $this->mappingRepository->table($remoteId),
                    $fieldName,
                    $this->mappingRepository->get($remoteId),
                    $relations
                );
            }
        }
    }

    /**
     * Processing data with data handler.
     *
     * @param array $data Data in data handler format
     * @param array $cmd Command in data handler format
     * @return bool
     * @throws Exception
     */
    private function dataHandling(array $data = [], array $cmd = [])
    {
        if (!empty($data)) {
            $this->dataHandler->start($data, []);
            $this->dataHandler->process_datamap();
        } elseif (!empty($cmd)) {
            $this->dataHandler->start([], $cmd);
            $this->dataHandler->process_cmdmap();
        }

        if (!empty($this->dataHandler->errorLog)) {
            return false;
        }

        return true;
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
     * @param array $recordData
     * @param string|null $tableName
     * @return ResponseInterface
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function updateRecord(
        InterestRequestInterface $request,
        array $recordData = [],
        ?string $tableName = null
    ): ResponseInterface {
        [
            'remoteId' => $remoteId,
            'data' => $importData
        ] = !empty($recordData) ? $recordData : $this->createArrayFromJson($request->getBody()->getContents());

        $responseFactory = $this->objectManager->getResponseFactory();
        $tableName = $tableName ?? $request->getResourceType()->__toString();

        if (!$this->mappingRepository->exists($remoteId)) {
            throw new NotFoundException(
                'Remote ID "' . $remoteId . '" does not exist.',
                $request
            );
        }

        $fieldsNotInTca = array_diff_key($importData, $GLOBALS['TCA'][$tableName]['columns']);
        if (count($fieldsNotInTca) > 0) {
            throw new ConflictException(
                'Unknown field(s) in field list: ' . implode(', ', array_keys($fieldsNotInTca)),
                $request
            );
        }

        $this->executeDataInsertOrUpdate(
            $tableName,
            (string)$this->mappingRepository->get($remoteId),
            $remoteId,
            $importData
        );

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
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function createOrUpdateRecord(InterestRequestInterface $request): ResponseInterface
    {
        $recordData = $this->createArrayFromJson($request->getBody()->getContents());

        // Passing record data as second argument because can't get request body from createRecord and updateRecord
        // TODO: What the reason?
        if (!$this->mappingRepository->exists($recordData['remoteId'])) {
            return $this->createRecord($request, $recordData);
        }

        return $this->updateRecord($request, $recordData);
    }

    /**
     * @param InterestRequestInterface $request
     * @param array $recordData
     * @param string|null $tableName
     * @return ResponseInterface
     * @throws NotFoundException
     */
    public function deleteRecord(
        InterestRequestInterface $request,
        array $recordData = [],
        ?string $tableName = null
    ): ResponseInterface {
        $this->setCurrentRequest($request);

        $tableName = $tableName ?? $request->getResourceType()->__toString();
        ExtensionManagementUtility::allowTableOnStandardPages($tableName);

        $deleteRecordData
            = (!empty($recordData)) ? $recordData : $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();

        if (!$this->mappingRepository->exists($deleteRecordData['remoteId'])) {
            throw new NotFoundException(
                'The remoteId "' . $deleteRecordData['remoteId'] . '" doesn\'t exist',
                $request
            );
        }

        $cmd[$this->mappingRepository->get($deleteRecordData['remoteId'])]['delete'] = 1;

        if (!$this->dataHandling([], $cmd)) {
            return $responseFactory->createErrorResponse(
                [
                    'status' => 'failure',
                    'message' => 'Error occured during data handling process. ' .
                        '(' . implode(', ', $this->dataHandler->errorLog) . ')',
                ],
                403,
                $request
            );
        }

        $this->mappingRepository->remove($deleteRecordData['remoteId']);
        $this->pendingRelationsRepository->removeRemote($deleteRecordData['remoteId']);

        return $responseFactory->createSuccessResponse(['status' => 'success'], 200, $request);
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function readRecords(InterestRequestInterface $request): ResponseInterface
    {
        $this->setCurrentRequest($request);

        $tableName = $request->getResourceType()->__toString();
        ExtensionManagementUtility::allowTableOnStandardPages($tableName);
        $queryBuilder = $this->objectManager->getQueryBuilder($tableName);
        $responseFactory = $this->objectManager->getResponseFactory();

        // TODO: Add permission check.
        // TODO: Add limit and pagination option
        $data = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->execute()
            ->fetchAllAssociative();

        return $responseFactory->createSuccessResponse(
            ['status' => 'success', 'data' => $data],
            200,
            $request
        );
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

    /**
     * Returns true if the field is a relation.
     *
     * @param string $table
     * @param string $field
     * @param string $remoteId
     * @param array $data
     * @return bool
     */
    protected function isRelationField(string $table, string $field, string $remoteId, array $data): bool
    {
        $typeField = (string)$GLOBALS['TCA'][$table]['ctrl']['type'];

        $fieldTcaConfiguration = BackendUtility::getTcaFieldConfiguration($table, $field);

        // Has type field
        if ($typeField !== '') {
            if (array_key_exists($typeField, $data)) {
                $typeValue = (string)$data[$typeField];
            } else {
                $typeValue = $this->getTypeValue($table, $remoteId);
            }

            $tcaTypes = $GLOBALS['TCA'][$table]['types'];

            if (isset($tcaTypes[$typeValue]['columnsOverrides'][$field]['config'])) {
                ArrayUtility::mergeRecursiveWithOverrule(
                    $fieldTcaConfiguration,
                    $tcaTypes[$typeValue]['columnsOverrides'][$field]['config']
                );
            }
        }

        return (
                $fieldTcaConfiguration['type'] === 'group'
                && $fieldTcaConfiguration['internal_type'] === 'db'
            )
            || (
                in_array($fieldTcaConfiguration['type'], ['inline', 'select'], true)
                && isset($fieldTcaConfiguration['foreign_table'])
            );
    }

    /**
     * Returns the type value of the local record representing $remoteId.
     *
     * @param string $table
     * @param string $remoteId
     * @return string The type value or '0' if not set or found.
     */
    protected function getTypeValue(string $table, string $remoteId): string
    {
        if (isset($this->getTypeValueCache[$table . '_' . $remoteId])) {
            return $this->getTypeValueCache[$table . '_' . $remoteId];
        }

        if (!$this->mappingRepository->exists($remoteId)) {
            $this->getTypeValueCache[$table . '_' . $remoteId] = '0';

            return '0';
        }

        $this->getTypeValueCache[$table . '_' . $remoteId] = (string)BackendUtility::getRecord(
            $table,
            $this->mappingRepository->get($remoteId),
            $GLOBALS['TCA'][$table]['ctrl']['type']
        ) ?? '0';

        return $this->getTypeValueCache[$table . '_' . $remoteId];
    }

    /**
     * Returns TCA configuration for a field with type-related overrides.
     *
     * @param string $table
     * @param string $field
     * @param array $row
     * @return array
     */
    protected function getTcaFieldConfigurationAndRespectColumnsOverrides(
        string $table,
        string $field,
        array $row
    ): array {
        $tcaFieldConf = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
        $recordType = BackendUtility::getTCAtypeValue($table, $row);
        $columnsOverridesConfigOfField
            = $GLOBALS['TCA'][$table]['types'][$recordType]['columnsOverrides'][$field]['config'] ?? null;

        if ($columnsOverridesConfigOfField) {
            ArrayUtility::mergeRecursiveWithOverrule($tcaFieldConf, $columnsOverridesConfigOfField);
        }

        return $tcaFieldConf;
    }
}
