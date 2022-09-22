<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
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
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
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
     * @var string
     */
    protected string $table;

    /**
     * @var string
     */
    protected string $remoteId;

    /**
     * @var array
     */
    protected array $data;

    /**
     * @var int
     */
    protected int $uid = 0;

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
     * @var string|null
     */
    protected ?string $workspace;

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
     * @param array $data
     * @param string $table
     * @param string $remoteId
     * @param string|null $language as RFC 1766/3066 string, e.g. nb or sv-SE.
     * @param string|null $workspace workspace represented with a remote ID.
     * @param array|null $metaData any additional data items not to be persisted but used in processing.
     *
     * @throws StopRecordOperationException is re-thrown from BeforeRecordOperationEvent handlers
     */
    public function __construct(
        RecordRepresentation $recordRepresentation,
        ?array $metaData = []
    ) {
        $this->recordRepresentation = $recordRepresentation;
        $this->table = strtolower($this->recordRepresentation->getRecordInstanceIdentifier()->getTable());
        $this->remoteId = $this->recordRepresentation->getRecordInstanceIdentifier()->getRemoteIdWithAspects();
        $this->data = $this->recordRepresentation->getData();
        $this->metaData = $metaData ?? [];
        $this->workspace = $this->recordRepresentation->getRecordInstanceIdentifier()->getWorkspace();

        $this->configurationProvider = GeneralUtility::makeInstance(ConfigurationProvider::class);

        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $this->pendingRelationsRepository = GeneralUtility::makeInstance(PendingRelationsRepository::class);

        $this->language = $this->recordRepresentation->getRecordInstanceIdentifier()->getLanguage();

        $this->createTranslationFields();

        $this->uid = $this->resolveUid();

        $this->contentObjectRenderer = $this->createContentObjectRenderer();

        if (isset($this->getData()['pid']) || $this instanceof CreateRecordOperation) {
            $this->storagePid = $this->resolveStoragePid();
        }

        $this->hash = md5(static::class . serialize($this->getArguments()));

        try {
            CompatibilityUtility::dispatchEvent(new BeforeRecordOperationEvent($this));
        } catch (StopRecordOperationException $exception) {
            $this->operationStopped = true;

            throw $exception;
        }

        $this->validateFieldNames();

        $this->contentObjectRenderer->data['language']
            = $this->getLanguage() === null ? null : $this->getLanguage()->getHreflang();

        $this->applyFieldDataTransformations();

        $this->prepareRelations();

        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->dataHandler->start([], []);

        if (!isset($this->getData()['pid']) && $this instanceof CreateRecordOperation) {
            $this->data['pid'] = $this->storagePid;
        }
    }

    public function __invoke()
    {
        if ($this->operationStopped) {
            return;
        }

        if (count($this->dataHandler->datamap) > 0) {
            $this->dataHandler->process_datamap();
        }

        if (count($this->dataHandler->cmdmap) > 0) {
            $this->dataHandler->process_cmdmap();
        }

        if (!empty($this->dataHandler->errorLog)) {
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

            $this->setUid($this->mappingRepository->get($this->remoteId));
        } else {
            $this->mappingRepository->update($this);
        }

        $this->persistPendingRelations();

        CompatibilityUtility::dispatchEvent(new AfterRecordOperationEvent($this));
    }

    /**
     * Returns the arguments as they would have been supplied to the constructor.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [
            $this->getData(),
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
    private function validateFieldNames(): void
    {
        $fieldsNotInTca = array_diff_key($this->getData(), $GLOBALS['TCA'][$this->getTable()]['columns'] ?? []);

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
    private function resolveStoragePid(): int
    {
        if (($GLOBALS['TCA'][$this->getTable()]['ctrl']['rootLevel'] ?? null) === 1) {
            return 0;
        }

        if (isset($this->getData()['pid'])) {
            if (!$this->mappingRepository->exists((string)$this->getData()['pid'])) {
                throw new NotFoundException(
                    'Unable to set PID. The remote ID "' . $this->getData()['pid'] . '" does not exist.',
                    1634205352895
                );
            }

            return $this->mappingRepository->get((string)$this->getData()['pid']);
        }

        $settings = $this->configurationProvider->getSettings();

        $pid = $this->contentObjectRenderer->stdWrap(
            $settings['persistence.']['storagePid'] ?? '',
            $settings['persistence.']['storagePid.'] ?? []
        );

        if ($pid === '') {
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
     * Resolves a site language. If no language is defined, the sites's default language will be returned. If the
     * storagePid has no site, null will be returned.
     *
     * @param string|null $language
     * @return SiteLanguage|null
     * @throws InvalidArgumentException
     */
    protected function resolveLanguage(?string $language): ?SiteLanguage
    {
        if (!TcaUtility::isLocalizable($this->getTable()) || empty($language)) {
            return null;
        }

        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $sites = $siteFinder->getAllSites();

        $siteLanguages = [];

        foreach ($sites as $site) {
            $siteLanguages = array_merge($siteLanguages, $site->getAllLanguages());
        }

        // This is the equivalent of running array_unique, but supports objects.
        $siteLanguages = array_reduce($siteLanguages, function (array $uniqueSiteLanguages, SiteLanguage $item) {
            /** @var SiteLanguage $siteLanguage */
            foreach ($uniqueSiteLanguages as $siteLanguage) {
                if ($siteLanguage->getLanguageId() === $item->getLanguageId()) {
                    return $uniqueSiteLanguages;
                }
            }

            $uniqueSiteLanguages[] = $item;

            return $uniqueSiteLanguages;
        }, []);

        foreach ($siteLanguages as $siteLanguage) {
            $hreflang = $siteLanguage->getHreflang();

            // In case this is the short form, e.g. "nb" or "sv", not "nb-NO" or "sv-SE".
            if (strlen($language) === 2) {
                $hreflang = substr($hreflang, 0, 2);
            }

            if (strtolower($hreflang) === strtolower($language)) {
                return $siteLanguage;
            }
        }

        throw new InvalidArgumentException(
            'The language "' . $language . '" is not defined in this TYPO3 instance.'
        );
    }

    /**
     * Resolves the UID for the remote ID.
     *
     * @return int
     * @throws ConflictException
     */
    protected function resolveUid(): int
    {
        if (
            $this->mappingRepository->exists($this->getRemoteId())
            && $this->mappingRepository->table($this->getRemoteId()) !== $this->getTable()
        ) {
            throw new ConflictException(
                'The remote ID "' . $this->getRemoteId() . '" exists, '
                . 'but doesn\'t belong to the table "' . $this->getTable() . '".',
                1634213051764
            );
        }

        return $this->mappingRepository->get($this->getRemoteId());
    }

    /**
     * @return ContentObjectRenderer
     */
    private function createContentObjectRenderer(): ContentObjectRenderer
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
            'data' => $this->getData(),
        ];

        return $contentObjectRenderer;
    }

    /**
     * Applies field value transformations defined in `tx_interest.transformations.<tableName>.<fieldName>`.
     */
    private function applyFieldDataTransformations(): void
    {
        $settings = $this->configurationProvider->getSettings();

        foreach ($settings['transformations.'][$this->getTable() . '.'] ?? [] as $fieldName => $configuration) {
            $settings['transformations.'][$this->getTable() . '.'][$fieldName] = $this->contentObjectRenderer->stdWrap(
                $this->data[substr($fieldName, 0, -1)] ?? '',
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
        foreach ($this->data as $fieldName => $fieldValue) {
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

        return ($tca['maxitems'] ?? 0) === 1 && empty($tca['foreign_table']);
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
            $this->getData(),
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
            && !isset($this->data[TcaUtility::getLanguageField($this->getTable())])
        ) {
            $baseLanguageRemoteId = $this->mappingRepository->removeAspectsFromRemoteId($this->getRemoteId());

            $this->remoteId = $this->mappingRepository->addAspectsToRemoteId($this->getRemoteId(), $this);

            $this->data[TcaUtility::getLanguageField($this->getTable())] = $this->getLanguage()->getLanguageId();

            $transOrigPointerField = TcaUtility::getTransOrigPointerField($this->getTable());
            if (!empty($transOrigPointerField) && !isset($this->data[$transOrigPointerField])) {
                $this->data[$transOrigPointerField] = $baseLanguageRemoteId;
            }

            $translationSourceField = TcaUtility::getTranslationSourceField($this->getTable());
            if (!empty($translationSourceField) && !isset($this->data[$translationSourceField])) {
                $this->data[$translationSourceField] = $baseLanguageRemoteId;
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
        return $this->table;
    }

    /**
     * @return string
     */
    public function getRemoteId(): string
    {
        return $this->remoteId;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * @param int $uid
     */
    public function setUid(int $uid)
    {
        $this->uid = $uid;
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
        return $this->language;
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
     * Detects and adds pending relations to `$this->pendingRelations`.
     *
     * @param string $fieldName
     * @param array $fieldValue
     * @param bool $prefixWithTable
     */
    private function detectPendingRelations(string $fieldName, array $fieldValue, bool $prefixWithTable)
    {
        $this->data[$fieldName] = [];
        foreach ($fieldValue as $remoteIdRelation) {
            if ($this->mappingRepository->exists($remoteIdRelation)) {
                $uid = $this->mappingRepository->get($remoteIdRelation);

                if ($prefixWithTable) {
                    $uid = $this->mappingRepository->table($remoteIdRelation) . '_' . $uid;
                }

                $this->data[$fieldName][] = $uid;

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
            is_array($this->data[$fieldName])
            && $this->contentObjectRenderer->stdWrap(
                $type,
                $this
                    ->configurationProvider
                    ->getSettings()['relationTypeOverride.'][$this->getTable() . '.'][$fieldName . '.']
                ?? []
            ) === 'inline'
        ) {
            $this->data[$fieldName] = implode(',', $this->data[$fieldName]);
        }
    }

    /**
     * Transform single-value array into scalar value to prevent Data Handler error.
     */
    protected function reduceSingleValueArrayToScalar(): void
    {
        foreach (array_keys($this->data) as $fieldName) {
            $this->reduceFieldSingleValueArrayToScalar($fieldName);
        }
    }

    /**
     * Transform single-value array into scalar value to prevent Data Handler error.
     */
    protected function reduceFieldSingleValueArrayToScalar(string $fieldName): void
    {
        foreach ($this->data as $fieldName => $fieldValue) {
            if (is_array($fieldValue) && count($fieldValue) <= 1) {
                if (
                    $fieldValue === []
                    && (
                        $fieldName === TcaUtility::getTranslationSourceField($this->getTable())
                        || $fieldName === TcaUtility::getTransOrigPointerField($this->getTable())
                    )
                ) {
                    $this->data[$fieldName] = 0;

                    continue;
                }

                $this->data[$fieldName] = $fieldValue[array_key_first($fieldValue)];

                // Unset empty single-relation fields (1:n) in new records.
                if (count($fieldValue) === 0 && $this->isSingleRelationField($fieldName) && $this->getUid() === 0) {
                    unset($this->data[$fieldName]);
                }
            }

            if ($this->data[$fieldName] === null) {
                unset($this->data[$fieldName]);
            }
        }
    }
}
