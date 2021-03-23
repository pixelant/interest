<?php
declare(strict_types=1);

namespace Pixelant\Interest\Handler;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Pixelant\Interest\Http\InterestRequestInterface;
use Pixelant\Interest\ObjectManagerInterface;
use Pixelant\Interest\Router\Route;
use Pixelant\Interest\Router\RouterInterface;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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
     * CrudHandler constructor.
     * @param ObjectManagerInterface $objectManager
     * @param DataHandler $dataHandler
     */
    public function __construct(ObjectManagerInterface $objectManager, DataHandler $dataHandler)
    {
        $this->objectManager = $objectManager;
        $this->dataHandler = $dataHandler;
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

        // Check if Id exists

        $configuration = $this->objectManager->getConfigurationProvider()->getSettings();
        $placeholderId = StringUtility::getUniqueId('NEW');
        $tableName = $request->getResourceType()->__toString();
        $responseFactory = $this->objectManager->getResponseFactory();
        $pendingRelations = [];

        // Add current table to allowed.
        ExtensionManagementUtility::allowTableOnStandardPages($tableName);

        if (!empty($importData)) {
            foreach ($importData as $fieldName => $values) {
                if (is_array($values)){
                    $pendingRelations[$fieldName] = $values;
                    unset($importData[$fieldName]);
                }
            }
        }

        $importData['pid'] = $configuration['persistence']['storagePid'];
        $data[$tableName][$placeholderId] = $importData;

        if (!$this->dataHandling($data)) {
            return $responseFactory->createErrorResponse(
                ['Error occured during data handling process, please check if data is valid'],
                403,
                $request
            );
        }

        $this->createRemoteIdLocalIdRelation(
            $remoteId,
            $tableName,
            $this->dataHandler->substNEWwithIDs[$placeholderId]
        );

        if (!empty($pendingRelations)) {
            foreach ($pendingRelations as $fieldName => $values) {
                foreach ($values as $key => $value) {
                    $this->createNonExistingRelationRecord(
                        $value,
                        $tableName,
                        $fieldName,
                        $this->dataHandler->substNEWwithIDs[$placeholderId],
                        CsvUtility::csvValues($values,',','')
                    );
                }
            }
        }

        $this->checkForNonExistingRelationRecords(
            $remoteId,
            $this->dataHandler->substNEWwithIDs[$placeholderId]
        );

        return $responseFactory->createSuccessResponse(
            [
                'status' => 'success',
                'data' => [
                    'uid' => $this->dataHandler->substNEWwithIDs[$placeholderId]
                ]
            ],
            200,
            $request
        );
    }

    /**
     * Generates random string for data handler.
     *
     * @return string
     */
    private function generateRandomString(): string
    {
        $randomizer = $this->objectManager->get(Random::class);
        return $randomizer->generateRandomHexString(8);
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
     * Checks if exists relation in relation mapping table for given remoteId
     *
     * @param string $remoteId
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function checkIfRelationExists(string $remoteId): bool
    {
        $queryBuilder = $this->objectManager->getQueryBuilder(self::REMOTE_ID_MAPPING_TABLE);
        $data = $queryBuilder
            ->count('uid')
            ->from(self::REMOTE_ID_MAPPING_TABLE)
            ->where(
                $queryBuilder->expr()->eq('remote_id', "'".$remoteId."'")
            )
            ->execute()
            ->fetchOne();

        return $data > 0;
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
        $tableName = $request->getResourceType()->__toString();
        ExtensionManagementUtility::allowTableOnStandardPages($tableName);
        $responseFactory = $this->objectManager->getResponseFactory();
        $updateRecordData = (!empty($recordData)) ? $recordData : $this->createArrayFromJson($request->getBody()->getContents());
        if (!$this->checkIfRelationExists($updateRecordData['remoteId'])){
            return $responseFactory->createErrorResponse(['RemoteID not found in DB'], 404, $request);
        }

        $remoteIdLocalIdRelationData = $this->getRemoteIdLocalIdRelation($updateRecordData['remoteId']);
        $filteredData = [];

        if (!empty($updateRecordData['data'])){
            foreach ($updateRecordData['data'] as $fieldName => $values){
                if (is_array($values)){
                    foreach ($values as $key => $value){
                        if (!$this->checkIfRelationExists($value)){
                            $this->createNonExistingRelationRecord(
                                $value,
                                $remoteIdLocalIdRelationData[0]['table'],
                                $fieldName,
                                $remoteIdLocalIdRelationData[0]['uid_local'],
                                CsvUtility::csvValues($values,',','')
                            );
                        } else {
                            $filteredData[$fieldName] = $values;
                        }
                    }
                } else {
                    $filteredData[$fieldName] = $values;
                }
            }
        }


        if (!empty($filteredData)){
            $dataHandlerData = [];

            foreach ($filteredData as $fieldName => $values){
                if (is_array($values)){
                    foreach ($values as $relation){
                        $dataHandlerData[$fieldName][] = $this->getRemoteIdLocalIdRelation($relation)[0]['uid_local'];
                    }
                } else {
                    $dataHandlerData[$fieldName] = $values;
                }
            }

            foreach ($dataHandlerData as $fieldname => $value){
                $tcaConfiguration = $GLOBALS['TCA'][$tableName]['columns'][$fieldname]['config'];

                if ($tcaConfiguration['type'] === 'inline'){
                    $dataHandlerData[$fieldname] = CsvUtility::csvValues($value,',','');
                }
            }

            $data[$remoteIdLocalIdRelationData[0]['table']][$remoteIdLocalIdRelationData[0]['uid_local']] = $dataHandlerData;
            if ($this->dataHandling($data)){
                return $responseFactory->createSuccessResponse(['status' => 'success'], 200, $request);
            } else {
                return $responseFactory->createErrorResponse(
                    ['Error occured during data handling process, please check if data is valid'],
                    403,
                    $request);
            }

        }

        return $responseFactory->createSuccessResponse(['status' => 'success'], 200, $request);
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
        if (!$this->checkIfRelationExists($recordData['remoteId'])){
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

        if (!$this->checkIfRelationExists($deleteRecordData['remoteId'])){
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
}
