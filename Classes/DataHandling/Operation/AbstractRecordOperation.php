<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Exception\ConflictException;
use Pixelant\Interest\DataHandling\Operation\Exception\DataHandlerErrorException;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\CompatibilityUtility;
use Pixelant\Interest\Utility\DatabaseUtility;
use Pixelant\Interest\Utility\RelationUtility;
use Pixelant\Interest\Utility\TcaUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Abstract class for handling record operations like create, delete, read, and update.
 */
abstract class AbstractRecordOperation
{
    /**
     * @var array
     */
    protected array $dataForDataHandler;

    /**
     * @var int
     */
    protected int $storagePid;

    /**
     * Language to use for processing.
     *
     * @var SiteLanguage|null
     */
    protected ?SiteLanguage $language;

    /**
     * @var ContentObjectRenderer
     */
    protected ContentObjectRenderer $contentObjectRenderer;

    /**
     * Additional data items not to be persisted but used in processing.
     *
     * @var array
     */
    protected array $metaData;

    /**
     * @var RemoteIdMappingRepository
     */
    protected RemoteIdMappingRepository $mappingRepository;

    /**
     * @var ConfigurationProvider
     */
    protected ConfigurationProvider $configurationProvider;

    /**
     * @var PendingRelationsRepository
     */
    protected PendingRelationsRepository $pendingRelationsRepository;

    /**
     * @var array
     */
    protected array $pendingRelations = [];

    /**
     * @var DataHandler
     */
    protected DataHandler $dataHandler;

    /**
     * Set to true if a DeferRecordOperationException is thrown. Means __destruct() will end early.
     *
     * @var bool
     */
    protected bool $operationStopped = false;

    /**
     * @var array|null
     */
    protected static ?array $getTypeValueCache = null;

    /**
     * The hash of this operation when it was initialized. Used to avoid repetition.
     *
     * @var string
     */
    protected string $hash;

    /**
     * @var RecordRepresentation
     */
    protected RecordRepresentation $recordRepresentation;

    /**
     * @var array
     */
    protected array $updatedForeignFieldValues = [];

    public function __invoke()
    {
        if ($this->operationStopped) {
            return;
        }

        if ($this instanceof UpdateRecordOperation) {
            $this->detectUpdatedForeignFieldValues();
        }

        if (count($this->dataHandler->datamap) > 0) {
            $this->dataHandler->process_datamap();
        }

        if ($this instanceof UpdateRecordOperation && count($this->updatedForeignFieldValues) > 0) {
            $this->processUpdatedForeignFieldValues();
        }

        if (count($this->dataHandler->cmdmap) > 0) {
            $this->dataHandler->process_cmdmap();
        }

        if (count($this->dataHandler->errorLog) > 0) {
            throw new DataHandlerErrorException(
                'Error occurred during the data handling: ' . implode(', ', $this->dataHandler->errorLog)
                . ' Datamap: ' . json_encode($this->dataHandler->datamap)
                . ' Cmdmap: ' . json_encode($this->dataHandler->cmdmap),
                1634296039450
            );
        }

        if ($this instanceof CreateRecordOperation && $this->getUid() === 0) {
            $this->setUid($this->dataHandler->substNEWwithIDs[array_key_first($this->dataHandler->substNEWwithIDs)]);
        }

        if (
            $this instanceof CreateRecordOperation
            || (
                // The UID might have been set by another operation already (e.g. a file), but not added to mapping.
                !$this->mappingRepository->exists($this->getRemoteId())
                && $this->getUid() > 0
            )
        ) {
            $this->mappingRepository->add(
                $this->getRemoteId(),
                $this->getTable(),
                // This assumes we have only done a single operation and there is only one NEW key.
                // The UID might have been set by another operation already, even though this is CreateRecordOperation.
                $this->getUid(),
                $this
            );

            $this->setUid(
                $this->mappingRepository->get(
                    $this->getRecordRepresentation()->getRecordInstanceIdentifier()->getRemoteIdWithAspects()
                )
            );
        } else {
            $this->mappingRepository->update($this);
        }

        $this->persistPendingRelations();

        GeneralUtility::makeInstance(EventDispatcher::class)->dispatch(new AfterRecordOperationEvent($this));
    }

    /**
     * Returns the arguments as they would have been supplied to the constructor.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [
            $this->getDataForDataHandler(),
            $this->getTable(),
            $this->getRemoteId(),
            $this->getLanguage() === null ? null : $this->getLanguage()->getHreflang(),
            null,
            $this->getMetaData(),
        ];
    }

    /**
     * Checks that all field names in $this->data are actually defined.
     *
     * @throws ConflictException
     */
    protected function validateFieldNames(): void
    {
        $fieldsNotInTca = array_diff_key(
            $this->getDataForDataHandler(),
            $GLOBALS['TCA'][$this->getTable()]['columns'] ?? []
        );

        if (count(array_diff(array_keys($fieldsNotInTca), ['pid'])) > 0) {
            throw new ConflictException(
                'Unknown field(s) in field list: ' . implode(', ', array_keys($fieldsNotInTca)),
                1634119601036
            );
        }
    }

    /**
     * Resolve the storage PID from `tx_interest.persistence.storagePid`. Accepts stdWrap.
     *
     * @return int
     * @throws NotFoundException
     * @throws InvalidArgumentException
     */
    protected function resolveStoragePid(): int
    {
        if (($GLOBALS['TCA'][$this->getTable()]['ctrl']['rootLevel'] ?? null) === 1) {
            return 0;
        }

        if (isset($this->getDataForDataHandler()['pid'])) {
            if (!$this->mappingRepository->exists((string)$this->getDataForDataHandler()['pid'])) {
                throw new NotFoundException(
                    'Unable to set PID. The remote ID "' . $this->getDataForDataHandler()['pid'] . '" does not exist.',
                    1634205352895
                );
            }

            return $this->mappingRepository->get((string)$this->getDataForDataHandler()['pid']);
        }

        $settings = $this->configurationProvider->getSettings();

        $pid = $this->contentObjectRenderer->stdWrap(
            $settings['persistence.']['storagePid'] ?? '',
            $settings['persistence.']['storagePid.'] ?? []
        );

        if ($pid === null || $pid === '') {
            $pid = 0;
        }

        if (!MathUtility::canBeInterpretedAsInteger($pid)) {
            throw new InvalidArgumentException(
                'The PID "' . $pid . '" is invalid and must be an integer.',
                1634213325242
            );
        }

        return (int)$pid;
    }

    /**
     * @return ContentObjectRenderer
     */
    protected function createContentObjectRenderer(): ContentObjectRenderer
    {
        if (CompatibilityUtility::typo3VersionIsLessThan('10')) {
            /** @var ContentObjectRenderer $contentObjectRenderer */
            $contentObjectRenderer = GeneralUtility::makeInstance(
                ContentObjectRenderer::class,
                GeneralUtility::makeInstance(TypoScriptFrontendController::class, null, 0, 0)
            );
        } else {
            $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        }

        $contentObjectRenderer->data = [
            'table' => $this->getTable(),
            'remoteId' => $this->getRemoteId(),
            'language' => null,
            'workspace' => null,
            'metaData' => $this->getMetaData(),
            'data' => $this->getDataForDataHandler(),
        ];

        return $contentObjectRenderer;
    }

    /**
     * Applies field value transformations defined in `tx_interest.transformations.<tableName>.<fieldName>`.
     */
    protected function applyFieldDataTransformations(): void
    {
        $settings = $this->configurationProvider->getSettings();

        foreach ($settings['transformations.'][$this->getTable() . '.'] ?? [] as $fieldName => $configuration) {
            $settings['transformations.'][$this->getTable() . '.'][$fieldName] = $this->contentObjectRenderer->stdWrap(
                $this->dataForDataHandler[substr($fieldName, 0, -1)] ?? '',
                $configuration
            );
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
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function prepareRelations()
    {
        foreach ($this->dataForDataHandler as $fieldName => $fieldValue) {
            if (!$this->isRelationalField($fieldName)) {
                continue;
            }

            if ($fieldName === 'pid') {
                $tcaConfiguration = TcaUtility::getFakePidTcaConfiguration();
            } else {
                $tcaConfiguration = $GLOBALS['TCA'][$this->getTable()]['columns'][$fieldName]['config'];
            }

            if (!is_array($fieldValue)) {
                $fieldValue = GeneralUtility::trimExplode(',', $fieldValue, true);
            } else {
                $fieldValue = array_filter($fieldValue);
            }

            $this->detectPendingRelations(
                $fieldName,
                $fieldValue,
                $this->isPrefixWithTable($tcaConfiguration)
            );

            $this->convertInlineRelationsValueToCsv($fieldName, $tcaConfiguration['type']);
        }

        // Transform single-value array into a scalar value to prevent Data Handler error.
        $this->reduceSingleValueArrayToScalar();
    }

    /**
     * Persists information about pending relations to the database.
     *
     * @see prepareRelations()
     */
    protected function persistPendingRelations(): void
    {
        foreach ($this->pendingRelations as $fieldName => $relations) {
            $this->pendingRelationsRepository->set(
                $this->mappingRepository->table($this->getRemoteId()),
                $fieldName,
                $this->mappingRepository->get($this->getRemoteId()),
                $relations
            );
        }
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
            self::$getTypeValueCache[$table . '_' . $remoteId] = '0';

            return '0';
        }

        self::$getTypeValueCache[$table . '_' . $remoteId] = BackendUtility::getTCAtypeValue(
            $table,
            DatabaseUtility::getRecord(
                $table,
                $this->mappingRepository->get($remoteId)
            )
        );

        return self::$getTypeValueCache[$table . '_' . $remoteId];
    }

    /**
     * Specifies if field must be processed as relational or not.
     *
     * @param string $field
     * @return bool
     */
    protected function isRelationalField(string $field): bool
    {
        if (
            $field === TcaUtility::getTranslationSourceField($this->getTable())
            || $field === TcaUtility::getTransOrigPointerField($this->getTable())
        ) {
            return true;
        }

        $settings = $this->configurationProvider->getSettings();

        if (
            isset($settings['relationOverrides.'][$this->getTable() . '.'][$field])
            || isset($settings['relationOverrides.'][$this->getTable() . '.'][$field . '.'])
        ) {
            return (bool)$this->contentObjectRenderer->stdWrap(
                $settings['relationOverrides.'][$this->getTable() . '.'][$field] ?? '',
                $settings['relationOverrides.'][$this->getTable() . '.'][$field . '.'] ?? []
            );
        }

        $tca = $this->getTcaFieldConfigurationAndRespectColumnsOverrides($field);

        return (
            $tca['type'] === 'group'
            && (
                ($tca['internal_type'] ?? null) === 'db'
                || isset($tca['allowed'])
            )
        )
        || (
            in_array($tca['type'], ['inline', 'select'], true)
            && isset($tca['foreign_table'])
        )
        || (
            $tca['type'] === 'category'
        );
    }

    /**
     * Returns true if the field contains a single n:1 relation without an MM table.
     *
     * @param string $field
     * @return bool
     */
    protected function isSingleRelationField(string $field): bool
    {
        if (!$this->isRelationalField($field)) {
            return false;
        }

        $settings = $this->configurationProvider->getSettings();

        if (
            isset($settings['isSingleRelationOverrides.'][$this->getTable() . '.'][$field])
            || isset($settings['isSingleRelationOverrides.'][$this->getTable() . '.'][$field . '.'])
        ) {
            return (bool)$this->contentObjectRenderer->stdWrap(
                $settings['isSingleRelationOverrides.'][$this->getTable() . '.'][$field] ?? '',
                $settings['isSingleRelationOverrides.'][$this->getTable() . '.'][$field . '.'] ?? []
            );
        }

        $tca = $this->getTcaFieldConfigurationAndRespectColumnsOverrides($field);

        return ($tca['maxitems'] ?? 0) === 1 && (!isset($tca['foreign_table']) || $tca['foreign_table'] === '');
    }

    /**
     * Returns TCA configuration for a field with type-related overrides.
     *
     * @param string $field
     * @return array
     */
    protected function getTcaFieldConfigurationAndRespectColumnsOverrides(string $field): array
    {
        // Make sure single-value array is transformed into scalar value to prevent Data Handler error.
        $this->reduceFieldSingleValueArrayToScalar($field);
        return TcaUtility::getTcaFieldConfigurationAndRespectColumnsOverrides(
            $this->getTable(),
            $field,
            $this->getDataForDataHandler(),
            $this->getRemoteId()
        );
    }

    /**
     * Create the translation fields if the table is translatable, language is set and nonzero, and the language field
     * hasn't already been set.
     */
    protected function createTranslationFields()
    {
        if (
            $this->getLanguage() !== null
            && $this->getLanguage()->getLanguageId() !== 0
            && TcaUtility::isLocalizable($this->getTable())
            && !isset($this->dataForDataHandler[TcaUtility::getLanguageField($this->getTable())])
        ) {
            $baseLanguageRemoteId = $this->mappingRepository->removeAspectsFromRemoteId($this->getRemoteId());

            $this->dataForDataHandler[TcaUtility::getLanguageField($this->getTable())]
                = $this->getLanguage()->getLanguageId();

            $transOrigPointerField = TcaUtility::getTransOrigPointerField($this->getTable());
            if (
                $transOrigPointerField !== null
                && $transOrigPointerField !== ''
                && !isset($this->dataForDataHandler[$transOrigPointerField])
            ) {
                $this->dataForDataHandler[$transOrigPointerField] = $baseLanguageRemoteId;
            }

            $translationSourceField = TcaUtility::getTranslationSourceField($this->getTable());
            if (
                $translationSourceField !== null
                && $translationSourceField !== ''
                && !isset($this->dataForDataHandler[$translationSourceField])
            ) {
                $this->dataForDataHandler[$translationSourceField] = $baseLanguageRemoteId;
            }
        }
    }

    /**
     * Finds pending relations for a $remoteId record that is being inserted into the database and adds DataHandler
     * datamap array inserting any pending relations into the database as well.
     *
     * @param string|int $uid Could be a newly inserted UID or a temporary ID (e.g. NEW1234abcd)
     */
    protected function resolvePendingRelations($uid): void
    {
        foreach ($this->pendingRelationsRepository->get($this->getRemoteId()) as $pendingRelation) {
            RelationUtility::addResolvedPendingRelationToDataHandler(
                $this->dataHandler,
                $pendingRelation,
                $this->getTable(),
                $uid
            );
        }
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->recordRepresentation->getRecordInstanceIdentifier()->getTable();
    }

    /**
     * @return string
     */
    public function getRemoteId(): string
    {
        return $this->recordRepresentation->getRecordInstanceIdentifier()->getRemoteIdWithAspects();
    }

    /**
     * @return array
     * @deprecated Will be removed in v2. Use getDataForDataHandler() instead.
     */
    public function getData(): array
    {
        return $this->getDataForDataHandler();
    }

    /**
     * @param array $data
     * @deprecated Will be removed in v2. Use setDataForDataHandler() instead.
     */
    public function setData(array $data)
    {
        $this->setDataForDataHandler($data);
    }

    /**
     * @return array
     */
    public function getDataForDataHandler(): array
    {
        return $this->dataForDataHandler;
    }

    /**
     * @param array $dataForDataHandler
     */
    public function setDataForDataHandler(array $dataForDataHandler)
    {
        $this->dataForDataHandler = $dataForDataHandler;
    }

    /**
     * @return int
     */
    public function getUid(): int
    {
        return $this->recordRepresentation->getRecordInstanceIdentifier()->getUid();
    }

    /**
     * @param int $uid
     */
    public function setUid(int $uid)
    {
        $this->recordRepresentation->getRecordInstanceIdentifier()->setUid($uid);
    }

    /**
     * @return int
     */
    public function getStoragePid(): int
    {
        return $this->storagePid;
    }

    /**
     * @return SiteLanguage|null
     */
    public function getLanguage(): ?SiteLanguage
    {
        return $this->getRecordRepresentation()->getRecordInstanceIdentifier()->getLanguage();
    }

    /**
     * @return array
     */
    public function getMetaData(): array
    {
        return $this->metaData;
    }

    /**
     * @return ContentObjectRenderer
     */
    public function getContentObjectRenderer(): ContentObjectRenderer
    {
        return $this->contentObjectRenderer;
    }

    /**
     * Returns a standardized hash string representing the values of this invocation.
     *
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @return RecordRepresentation
     */
    public function getRecordRepresentation(): RecordRepresentation
    {
        return $this->recordRepresentation;
    }

    /**
     * Detects and adds pending relations to `$this->pendingRelations`.
     *
     * @param string $fieldName
     * @param array $fieldValue
     * @param bool $prefixWithTable
     */
    private function detectPendingRelations(string $fieldName, array $fieldValue, bool $prefixWithTable)
    {
        $this->dataForDataHandler[$fieldName] = [];
        foreach ($fieldValue as $remoteIdRelation) {
            if ($this->mappingRepository->exists($remoteIdRelation)) {
                $uid = $this->mappingRepository->get($remoteIdRelation);

                if ($prefixWithTable) {
                    $uid = $this->mappingRepository->table($remoteIdRelation) . '_' . $uid;
                }

                $this->dataForDataHandler[$fieldName][] = $uid;

                continue;
            }

            $this->pendingRelations[$fieldName][] = $remoteIdRelation;
        }
    }

    /**
     * Returns true if the configuration specifies that the field supports records from multiple tables, meaning that
     * the UID should be prefixed with the table name: table_name_123.
     *
     * @param array $tcaConfiguration
     * @return bool
     */
    protected function isPrefixWithTable(array $tcaConfiguration): bool
    {
        $prefixWithTable = false;

        if (
            $tcaConfiguration['type'] === 'group'
            && (
                $tcaConfiguration['allowed'] === '*'
                || strpos(',', $tcaConfiguration['allowed']) !== false
            )
        ) {
            $prefixWithTable = true;
        }

        return $prefixWithTable;
    }

    /**
     * Ensures that inline fields have the UIDs of IRRE records as a commaseparated value string.
     *
     * @param string $fieldName
     * @param string|int $type
     */
    protected function convertInlineRelationsValueToCsv(string $fieldName, $type): void
    {
        if (
            is_array($this->dataForDataHandler[$fieldName])
            && $this->contentObjectRenderer->stdWrap(
                $type,
                $this
                    ->configurationProvider
                    ->getSettings()['relationTypeOverride.'][$this->getTable() . '.'][$fieldName . '.']
                ?? []
            ) === 'inline'
        ) {
            $this->dataForDataHandler[$fieldName] = implode(',', $this->dataForDataHandler[$fieldName]);
        }
    }

    /**
     * Transform single-value array into scalar value to prevent Data Handler error.
     */
    protected function reduceSingleValueArrayToScalar(): void
    {
        foreach (array_keys($this->dataForDataHandler) as $fieldName) {
            $this->reduceFieldSingleValueArrayToScalar($fieldName);
        }
    }

    /**
     * Transform single-value array into scalar value to prevent Data Handler error.
     */
    protected function reduceFieldSingleValueArrayToScalar(string $fieldName): void
    {
        $fieldValue = $this->dataForDataHandler[$fieldName];

        if (is_array($fieldValue) && count($fieldValue) <= 1) {
            if (
                $fieldValue === []
                && (
                    $fieldName === TcaUtility::getTranslationSourceField($this->getTable())
                    || $fieldName === TcaUtility::getTransOrigPointerField($this->getTable())
                )
            ) {
                $this->dataForDataHandler[$fieldName] = 0;

                return;
            }

            $this->dataForDataHandler[$fieldName] = $fieldValue[array_key_first($fieldValue)] ?? null;

            // Unset empty single-relation fields (1:n) in new records.
            if (count($fieldValue) === 0 && $this->isSingleRelationField($fieldName) && $this->getUid() === 0) {
                unset($this->dataForDataHandler[$fieldName]);
            }
        }

        if (isset($this->dataForDataHandler[$fieldName]) && $this->dataForDataHandler[$fieldName] === null) {
            unset($this->dataForDataHandler[$fieldName]);
        }
    }

    /**
     * Check datamap fields with foreign field and store value(s) in array.
     * After process_datamap values can be used to compare what is actually
     * stored in the database and we can delete removed values.
     */
    protected function detectUpdatedForeignFieldValues(): void
    {
        foreach ($this->dataHandler->datamap[$this->getTable()] as $id => $data) {
            foreach ($data as $field => $value) {
                $tcaFieldConf = $this->getTcaFieldConfigurationAndRespectColumnsOverrides($field);
                if ($tcaFieldConf['foreign_field'] ?? false) {
                    $this->updatedForeignFieldValues[$this->getTable()][$id][$field] = $value;
                }
            }
        }
    }

    /**
     * Process updated foreign field values to find values to delete by
     * adding them to cmpmap.
     */
    protected function processUpdatedForeignFieldValues(): void
    {
        foreach ($this->updatedForeignFieldValues[$this->getTable()] as $id => $data) {
            foreach ($data as $field => $value) {
                $newValues = GeneralUtility::trimExplode(',', $value, true);
                $fieldRelations = RelationUtility::getRelationsFromField($this->getTable(), $id, $field);
                foreach ($fieldRelations as $relationTable => $relationTableValues) {
                    foreach ($relationTableValues as $relationTableValue) {
                        if (!in_array((string)$relationTableValue, $newValues, true)) {
                            $this->dataHandler->cmdmap[$relationTable][$relationTableValue]['delete'] = 1;
                        }
                    }
                }
            }
        }
    }

    public function __wakeup()
    {
        // Remove in v2. For compatibility with renamed property in deferred data.
        if (isset($this->data)) {
            $this->dataForDataHandler = $this->data;
        }
    }
}
