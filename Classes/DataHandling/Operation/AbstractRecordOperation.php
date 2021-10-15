<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\Configuration\ConfigurationProvider;
use Pixelant\Interest\Configuration\ConfigurationProviderInterface;
use Pixelant\Interest\DataHandling\Operation\Exception\ConflictException;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException;
use Pixelant\Interest\DataHandling\Operation\Exception\MissingArgumentException;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

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
     * @var int|null
     */
    private ?int $uid;

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

        $this->validateFieldNames();

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->configurationProvider = GeneralUtility::makeInstance(ConfigurationProvider::class);

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->pendingRelationsRepository = GeneralUtility::makeInstance(PendingRelationsRepository::class);

        $this->resolveUid = $this->resolveUid();

        $this->storagePid = $this->resolveStoragePid();

        $this->contentObjectRenderer = $this->createContentObjectRenderer();

        $this->language = $this->resolveLanguage((string)$language);

        $this->applyFieldDataTransformations();

        $this->prepareRelations();
    }

    /**
     * Checks that all field names in $this->data are actually defined.
     *
     * @throws ConflictException
     */
    private function validateFieldNames(): void
    {
        $fieldsNotInTca = array_diff_key($this->getData(), $GLOBALS['TCA'][$this->getTable()]['columns']);

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
            $settings['persistence.']['storagePid'] ?? []
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
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        try {
            $site = $siteFinder->getSiteByPageId($this->getStoragePid());
        } catch (SiteNotFoundException $siteNotFoundException) {
            if (empty($language)) {
                return null;
            }

            $sites = $siteFinder->getAllSites();

            $siteLanguages = [];

            foreach ($sites as $site) {
                $siteLanguages = array_merge($siteLanguages, $site->getAllLanguages());
            }

            $siteLanguages = array_unique($siteLanguages);

            $site = null;
        }

        if ($site !== null) {
            $siteLanguages = $site->getAllLanguages();
        }

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
    private function resolveUid(): ?int
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
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $contentObjectRenderer->data = [
            'table' => $this->getTable(),
            'remoteId' => $this->getRemoteId(),
            'language' => $this->getLanguage() === null ? null : $this->getLanguage()->getHreflang(),
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

            if ($tcaConfiguration['type'] === 'inline') {
                $this->data[$fieldName] = implode(',', $this->data[$fieldName]);
            }
        }

        // Transform single values array into $key => $value pair to prevent Data Handler error.
        foreach ($this->data as $fieldName => $fieldValue) {
            if (is_array($fieldValue)) {
                if (count($fieldValue) === 1) {
                    $value = $fieldValue[array_key_first($fieldValue)];
                    $this->data[$fieldName] = $value;
                }
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
        $tca = $this->getTcaFieldConfigurationAndRespectColumnsOverrides($field);

        return (
                $tca['config']['type'] === 'group'
                && $tca['config']['internal_type'] === 'db'
            )
            || (
                in_array($tca['config']['type'], ['inline', 'select'], true)
                && isset($tca['config']['foreign_table'])
            );
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
     * @return int|null
     */
    public function getUid(): ?int
    {
        return $this->uid;
    }

    /**
     * @param int|null $uid
     */
    public function setUid(?int $uid)
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
}
