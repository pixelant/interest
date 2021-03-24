<?php
declare(strict_types=1);

namespace Pixelant\Interest\Handler;

use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

class CrudHandler implements HandlerInterface
{
    const REMOTE_ID_MAPPING_TABLE = 'tx_interest_remote_id_mapping';

    const PENDING_RELATIONS_TABLE = 'tx_interest_pending_relations';

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
     *
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        DataHandler $dataHandler,
        RemoteIdMappingRepository $mappingRepository,
        PendingRelationsRepository $pendingRelationsRepository
    )
    {
        $this->objectManager = $objectManager;
        $this->dataHandler = $dataHandler;
        $this->mappingRepository = $mappingRepository;
        $this->pendingRelationsRepository = $pendingRelationsRepository;
    }

    /**
     * @param InterestRequestInterface $request
     * @param array $recordData
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     * @throws Exception
     */
    public function createRecord(InterestRequestInterface $request, array $recordData = [])
    {
        list(
            'remoteId' => $remoteId,
            'data' => $importData
        ) = !empty($recordData)
            ? $recordData
            : $this->createArrayFromJson($request->getBody()->getContents());

        $responseFactory = $this->objectManager->getResponseFactory();
        $tableName = $request->getResourceType()->__toString();

        if ($remoteId === null) {
            return $responseFactory->createErrorResponse(
                ['No remote ID given.'],
                404,
                $request
            );
        }

        if ($this->mappingRepository->exists($remoteId)) {
            return $responseFactory->createErrorResponse(
                ['Remote ID "' . $remoteId . '" already exists.'],
                409,
                $request
            );
        }

        $fieldsNotInTca = array_diff_key($importData, $GLOBALS['TCA'][$tableName]['columns']);
        if (count($fieldsNotInTca) > 0) {
            return $responseFactory->createErrorResponse(
                ['Unknown field(s) in field list: ' . implode(', ', array_keys($fieldsNotInTca))],
                409,
                $request
            );
        }

        $placeholderId = StringUtility::getUniqueId('NEW');

        $localId = $this->executeDataInsertOrUpdate($tableName, $placeholderId, $remoteId, $importData);

        return $responseFactory->createSuccessResponse(
            [
                'status' => 'success',
                'data' => [
                    'uid' => $localId
                ]
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
     * @return int The inserted UID
     */
    protected function executeDataInsertOrUpdate(
        string $tableName,
        string $localId,
        string $remoteId,
        array $recordData
    ): int
    {
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
            return $responseFactory->createErrorResponse(
                ['Error occured during data handling process, please check if data is valid'],
                403,
                $request
            );
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
    protected function resolvePendingRelations(string $table, string $remoteId, string $placeholderId, &$data)
    {
        foreach ($this->pendingRelationsRepository->get($remoteId) as $pendingRelation) {
            /** @var RelationHandler $relationHandler */
            $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

            $relationHandler->start(
                '',
                $tableName,
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
                $relationHandler->getFromDB()[$foreignTable] ?? [],
                'uid'
            );

            $data[$pendingRelation['table']][$pendingRelation['record_uid']][$pendingRelation['field']] =
                implode(',', array_merge($existingRelations, [$placeholderId]));
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
        }

        return $importData;
    }

    /**
     * Persists information about pending relations to the database.
     *
     * @see prepareRelations()
     */
    protected function persistPendingRelations()
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
     * Generates random string for data handler.
     *
     * @return string
     */
    private function generateRandomString(): string
    {
        return StringUtility::getUniqueId('NEW');
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
        if (!empty($data)){
            $this->dataHandler->start($data, []);
            $this->dataHandler->process_datamap();
        } else if (!empty($cmd)) {
            $this->dataHandler->start([], $cmd);
            $this->dataHandler->process_cmdmap();
        }

        if (!empty($this->dataHandler->errorLog)){
            return false;
        }

        return true;
    }

    /**
     * Creates record in relation mapping table.
     *
     * @param string $remoteId
     * @param string $table
     * @param int $uid_local
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function createRemoteIdLocalIdRelation(string $remoteId, string $table, int $uid_local)
    {
        $queryBuilder = $this->objectManager->getQueryBuilder(self::REMOTE_ID_MAPPING_TABLE);

        $queryBuilder
            ->insert(self::REMOTE_ID_MAPPING_TABLE)
            ->values([
                'remote_id' => $remoteId,
                'table' => $table,
                'uid_local' => $uid_local
            ])
            ->execute();
    }

    /**
     * Creates record in pending relations table.
     *
     * @param string $remoteId
     * @param string $table
     * @param string $field
     * @param int $record_uid
     * @param string $allFieldRelations
     * @param string $parentRemoteId
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function createNonExistingRelationRecord(
        string $remoteId,
        string $table,
        string $field,
        int $record_uid,
        string $allFieldRelations
    )
    {
        $queryBuilder = $this->objectManager->getQueryBuilder(self::PENDING_RELATIONS_TABLE);

        $queryBuilder
            ->insert(self::PENDING_RELATIONS_TABLE)
            ->values([
                'remote_id' => $remoteId,
                'table' => $table,
                'field' => $field,
                'record_uid' => $record_uid,
                'all_field_relations' => $allFieldRelations,
                'timestamp' => time()
            ])
            ->execute();

        $this->checkForNonExistingRelationRecords($remoteId, $record_uid);
    }

    /**
     * Returns record relative to given remoteId.
     *
     * @param string $remoteId
     * @param string $tablename
     * @return array
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function getRemoteIdLocalIdRelation(string $remoteId): array
    {
        $queryBuilder = $this->objectManager->getQueryBuilder(self::REMOTE_ID_MAPPING_TABLE);

        return $queryBuilder
            ->select('*')
            ->from(self::REMOTE_ID_MAPPING_TABLE)
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq('remote_id', "'".$remoteId."'")
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }

    /**
     * Checks if relation was added to pending relations and remove it from there and remove if it is.
     * Looking for all relation for given remoteId that can be removed from pending relations table.
     *
     * @param string $remoteId
     * @param int $uid_local
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function checkForNonExistingRelationRecords(string $remoteId, int $uid_local)
    {
        $queryBuilder = $queryBuilder = $this->objectManager->getQueryBuilder(self::PENDING_RELATIONS_TABLE);
        $relationsData = $queryBuilder
            ->select('*')
            ->from(self::PENDING_RELATIONS_TABLE)
            ->where(
                $queryBuilder->expr()->eq('remote_id', "'".$remoteId."'")
            )
            ->execute()
            ->fetchAllAssociative();

        foreach ($relationsData as $relation){
            $fieldRelations = CsvUtility::csvToArray($relation['all_field_relations']);
            $tcaConfiguration = $GLOBALS['TCA'][$relation['table']]['columns'][$relation['field']]['config'];

            $relationValues = [];

            if (!empty($fieldRelations)){
                foreach ($fieldRelations[0] as $remoteId){
                    $relationData = $this->getRemoteIdLocalIdRelation($remoteId);

                    if (!empty($relationData)){
                        foreach ($relationData[0] as $fieldName => $value){
                            if ($fieldName === 'uid_local'){
                                $relationValues[] = $value;
                            }
                        }
                    }
                }
            }

            if (!empty($relationValues)){

                $data[$relation['table']][$relation['record_uid']] = [
                    $relation['field'] => ($tcaConfiguration['type'] === 'inline') ? CsvUtility::csvValues($relationValues,',','') : $relationValues
                ];

                $this->dataHandling($data);
            }

            if (!empty($fieldRelations)){
                $queryBuilder = $this->objectManager->getQueryBuilder(self::REMOTE_ID_MAPPING_TABLE);
                $counter = 0;

                foreach ($fieldRelations[0] as $fieldRelation){
                    $relationCount = $queryBuilder
                        ->count('*')
                        ->from(self::REMOTE_ID_MAPPING_TABLE)
                        ->where(
                            $queryBuilder->expr()->eq('remote_id', "'".$fieldRelation."'")
                        )
                        ->execute()
                        ->fetchOne();

                    if ($relationCount > 0){
                        $counter++;
                    }
                }

                if ($counter == count($fieldRelations[0])){

                    $queryBuilder = $this->objectManager->getQueryBuilder(self::PENDING_RELATIONS_TABLE);

                    foreach ($fieldRelations[0] as $fieldRelation){
                        $queryBuilder
                            ->delete(self::PENDING_RELATIONS_TABLE)
                            ->where(
                                $queryBuilder->expr()->eq('remote_id', "'".$fieldRelation."'")
                            )
                            ->execute();
                    }
                }
            }
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

    /**
     * @param InterestRequestInterface $request
     * @param array $recordData
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     * @throws Exception
     */
    public function updateRecord(InterestRequestInterface $request, array $recordData = []): ResponseInterface
    {
        list(
            'remoteId' => $remoteId,
            'data' => $importData
        ) = !empty($recordData)
            ? $recordData
            : $this->createArrayFromJson($request->getBody()->getContents());

        $responseFactory = $this->objectManager->getResponseFactory();
        $tableName = $request->getResourceType()->__toString();

        if (!$this->mappingRepository->exists($remoteId)){
            return $responseFactory->createErrorResponse(
                ['Remote ID "' . $remoteId . '" does not exists.'],
                404,
                $request
            );
        }

        $fieldsNotInTca = array_diff_key($importData, $GLOBALS['TCA'][$tableName]['columns']);
        if (count($fieldsNotInTca) > 0) {
            return $responseFactory->createErrorResponse(
                ['Unknown field(s) in field list: ' . implode(', ', array_keys($fieldsNotInTca))],
                409,
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
                    'uid' => $this->mappingRepository->get($remoteId)
                ]
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
        if (!$this->mappingRepository->exists($recordData['remoteId'])){
            return $this->createRecord($request, $recordData);
        } else {
            return $this->updateRecord($request, $recordData);
        }
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function deleteRecord(InterestRequestInterface $request): ResponseInterface
    {
        $tableName = $request->getResourceType()->__toString();
        ExtensionManagementUtility::allowTableOnStandardPages($tableName);
        $deleteRecordData = $this->createArrayFromJson($request->getBody()->getContents());
        $responseFactory = $this->objectManager->getResponseFactory();

        if (!$this->mappingRepository->exists($deleteRecordData['remoteId'])){
            return $responseFactory->createErrorResponse(["Requested remoteId doesn't exists"], 404, $request);
        }

        $remoteIdLocalIdRelation = $this->getRemoteIdLocalIdRelation($deleteRecordData['remoteId']);

        $cmd[
            $remoteIdLocalIdRelation[0]['table']][$remoteIdLocalIdRelation[0]['uid_local']
        ]['delete'] = 1;

        if ($this->dataHandling([], $cmd)){
            $queryBuilder = $this->objectManager->getQueryBuilder(self::REMOTE_ID_MAPPING_TABLE);

            $queryBuilder
                ->delete(self::REMOTE_ID_MAPPING_TABLE)
                ->where(
                    $queryBuilder->expr()->eq('remote_id', "'".$deleteRecordData['remoteId']."'")
                )
                ->execute();

            return $responseFactory->createSuccessResponse(['status' => 'success'], 200, $request);

        } else {
            return $responseFactory->createErrorResponse(
                ['Error occured during data handling process, please check if data is valid'],
                403,
                $request);
        }
    }

    /**
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function readRecords(InterestRequestInterface $request): ResponseInterface
    {
        $tableName = $request->getResourceType()->__toString();
        ExtensionManagementUtility::allowTableOnStandardPages($tableName);
        $queryBuilder = $this->objectManager->getQueryBuilder($tableName);
        $responseFactory = $this->objectManager->getResponseFactory();

        $data = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->execute()
            ->fetchAllAssociative();

        if ($data){
            return $responseFactory->createSuccessResponse($data, 200, $request);
        } else {
            return $responseFactory->createErrorResponse(['No records found'], 404, $request);
        }
    }
    /**
     * @param RouterInterface $router
     * @param InterestRequestInterface $request
     */
    public function configureRoutes(RouterInterface $router, InterestRequestInterface $request)
    {
        $resourceType = $request->getResourceType()->__toString();
        $router->add(Route::post($resourceType, [$this, 'createRecord']));
        $router->add(Route::post($resourceType . '/update', [$this, 'updateRecord']));
        $router->add(Route::put($resourceType, [$this, 'createOrUpdateRecord']));
        $router->add(Route::delete($resourceType, [$this, 'deleteRecord']));
        $router->add(Route::get($resourceType, [$this, 'readRecords']));
    }

    /**
     * Returns true if the field is a relation
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
            if (key_exists($typeField, $data)) {
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
                in_array($fieldTcaConfiguration['type'], ['inline', 'select'])
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
    ): array
    {
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
