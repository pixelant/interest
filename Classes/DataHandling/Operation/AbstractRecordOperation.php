<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\Configuration\ConfigurationProviderInterface;
use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\DataHandling\Operation\Exception\ConflictException;
use Pixelant\Interest\DataHandling\Operation\Exception\DataHandlerErrorException;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException;
use Pixelant\Interest\DataHandling\Operation\Exception\MissingArgumentException;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\CompatibilityUtility;
use Pixelant\Interest\Utility\TcaUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ArrayUtility;
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
    private string $table;

    /**
     * @var string
     */
    private string $remoteId;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var int
     */
    private int $uid = 0;

    /**
     * @var int
     */
    private int $storagePid;

    /**
     * Language to use for processing.
     *
     * @var SiteLanguage|null
     */
    private ?SiteLanguage $language;

    /**
     * @var ContentObjectRenderer
     */
    private ContentObjectRenderer $contentObjectRenderer;

    /**
     * Additional data items not to be persisted but used in processing.
     *
     * @var array
     */
    private array $metaData;

    /**
     * @var RemoteIdMappingRepository
     */
    protected RemoteIdMappingRepository $mappingRepository;

    /**
     * @var ConfigurationProviderInterface
     */
    protected ConfigurationProviderInterface $configurationProvider;

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
     * @param array $data
     * @param string $table
     * @param string $remoteId
     * @param string|null $language as RFC 1766/3066 string, e.g. nb or sv-SE.
     * @param string|null $workspaceRemoteId workspace represented with a remote ID.
     * @param array|null $metaData any additional data items not to be persisted but used in processing.
     */
    public function __construct(
        array $data,
        string $table,
        string $remoteId,
        ?string $language = null,
        ?string $workspace = null,
        ?array $metaData = []
    )
    {
        $this->table = strtolower($table);
        $this->remoteId = $remoteId;
        $this->data = $data;
        $this->metaData = $metaData ?? [];

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->configurationProvider = GeneralUtility::makeInstance(ConfigurationProvider::class);

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->pendingRelationsRepository = GeneralUtility::makeInstance(PendingRelationsRepository::class);

        $this->language = $this->resolveLanguage((string)$language);

        $this->createTranslationFields();

        $this->uid = $this->resolveUid();

        $this->contentObjectRenderer = $this->createContentObjectRenderer();

        $this->storagePid = $this->resolveStoragePid();

        try {
            CompatibilityUtility::dispatchEvent(new BeforeRecordOperationEvent($this));
        } catch (StopRecordOperationException $exception) {
            $this->operationStopped = true;

            throw $exception;
        }

        $this->validateFieldNames();

        $this->contentObjectRenderer->data['language'] =
            $this->getLanguage() === null ? null : $this->getLanguage()->getHreflang();

        $this->applyFieldDataTransformations();

        $this->prepareRelations();

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->dataHandler->start([], []);

        $this->data['pid'] = $this->storagePid;
    }

    public function __destruct()
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
                'Error occured during the data handling: ' . implode(', ', $this->dataHandler->errorLog)
                . ' Datamap: ' . json_encode($this->dataHandler->datamap)
                . ' Cmdmap: ' . json_encode($this->dataHandler->cmdmap),
                1634296039450
            );
        }

        if ($this instanceof CreateRecordOperation) {
            $this->mappingRepository->add(
                $this->getRemoteId(),
                $this->getTable(),
                // The UID might have been set by another operation already, even though this is CreateRecordOperation.
                // This assumes we have only done a single operation and there is only one NEW key.
                $this->getUid() ?: $this->dataHandler->substNEWwithIDs[array_key_first($this->dataHandler->substNEWwithIDs)],
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
            $this->getMetaData()
        ];
    }

    /**
     * Checks that all field names in $this->data are actually defined.
     *
     * @throws ConflictException
     */
    private function validateFieldNames(): void
    {
        $fieldsNotInTca = array_diff_key($this->getData(), $GLOBALS['TCA'][$this->getTable()]['columns']) ?? [];

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
     * @throws ConflictException
     * @throws MissingArgumentException
     */
    private function resolveStoragePid(): int
    {
        if ($GLOBALS['TCA'][$this->getTable()]['ctrl']['rootLevel'] === 1) {
            return 0;
        }

        if (isset($this->getData()['pid'])) {
            if(!$this->mappingRepository->exists((string)$this->getData()['pid']))  {
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
     */
    private function resolveLanguage(?string $language): ?SiteLanguage
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
     * @return int|null
     * @throws ConflictException
     */
    private function resolveUid(): int
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
        /** @var ContentObjectRenderer $contentObjectRenderer */
        $contentObjectRenderer = GeneralUtility::makeInstance(
            ContentObjectRenderer::class,
            GeneralUtility::makeInstance(TypoScriptFrontendController::class, null, 0, 0)
        );

        $contentObjectRenderer->data = [
            'table' => $this->getTable(),
            'remoteId' => $this->getRemoteId(),
            'language' => null,
            'workspace' => null,
            'metaData' => $this->getMetaData(),
            'data' => $this->getData()
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
     * @param string $tableName
     * @param string $remoteId
     * @param array $importData Referenced array of import data (record fieldName => value pairs).
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function prepareRelations()
    {
        foreach ($this->data as $fieldName => $fieldValue) {
            if ($this->isRelationalField($fieldName)) {
                if (!is_array($fieldValue)) {
                    $fieldValue = GeneralUtility::trimExplode(',', $fieldValue, true);
                }

                $this->data[$fieldName] = [];
                foreach ($fieldValue as $remoteIdRelation) {
                    if ($this->mappingRepository->exists($remoteIdRelation)) {
                        $this->data[$fieldName][] = $this->mappingRepository->get($remoteIdRelation);

                        continue;
                    }

                    $this->pendingRelations[$fieldName][] = $remoteIdRelation;
                }
            }

            $tcaConfiguration = $GLOBALS['TCA'][$this->getTable()]['columns'][$fieldName]['config'];

            if (is_array($this->data[$fieldName]) && $tcaConfiguration['type'] === 'inline') {
                $this->data[$fieldName] = implode(',', $this->data[$fieldName]);
            }
        }

        // Transform single-value array into $key => $value pair to prevent Data Handler error.
        foreach ($this->data as $fieldName => $fieldValue) {
            if (is_array($fieldValue) && count($fieldValue) <= 1) {
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
                $this->getTcaFieldConfigurationAndRespectColumnsOverrides($pendingRelation['field'])
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

        $this->getTypeValueCache[$table . '_' . $remoteId] = BackendUtility::getTCAtypeValue(
            $table,
            BackendUtility::getRecord(
                $table,
                $this->mappingRepository->get($remoteId)
            )
        );

        return $this->getTypeValueCache[$table . '_' . $remoteId];
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

        if (isset($settings['relationOverrides.'][$this->getTable() . '.'][$field])) {
            return (bool)$settings['relationOverrides.'][$this->getTable() . '.'][$field];
        }

        $tca = $this->getTcaFieldConfigurationAndRespectColumnsOverrides($field);

        return (
                $tca['type'] === 'group'
                && $tca['internal_type'] === 'db'
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

        $tca = $this->getTcaFieldConfigurationAndRespectColumnsOverrides($field);

        return $tca['maxitems'] === 1 && empty($tca['foreign_table']);
    }

    /**
     * Returns TCA configuration for a field with type-related overrides.
     *
     * @param string $table
     * @param string $field
     * @param array $row
     * @return array
     */
    protected function getTcaFieldConfigurationAndRespectColumnsOverrides(string $field): array {
        $tcaFieldConf = $GLOBALS['TCA'][$this->getTable()]['columns'][$field]['config'];

        $data = $this->getData();

        if ($this->mappingRepository->exists($this->remoteId)) {
            $data = array_merge(
                BackendUtility::getRecord(
                    $this->table,
                    $this->mappingRepository->get($this->getRemoteId())
                ),
                $data
            );
        }

        $recordType = BackendUtility::getTCAtypeValue($this->getTable(), $data);

        $columnsOverridesConfigOfField
            = $GLOBALS['TCA'][$this->getTable()]['types'][$recordType]['columnsOverrides'][$field]['config'] ?? null;

        if ($columnsOverridesConfigOfField) {
            ArrayUtility::mergeRecursiveWithOverrule($tcaFieldConf, $columnsOverridesConfigOfField);
        }

        return $tcaFieldConf;
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
}
