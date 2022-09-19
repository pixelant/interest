<?php

/**
 * @noinspection SqlNoDataSourceInspection
 * @noinspection SqlDialectInspection
 */

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Cache\Backend\NullBackend;
use TYPO3\CMS\Core\Cache\Frontend\NullFrontend;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CreateRecordOperationTest extends AbstractRecordOperationFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ExtensionManagementUtility::loadExtTables();

        $siteConfiguration = new SiteConfiguration(
            GeneralUtility::getFileAbsFileName('EXT:interest/Tests/Functional/DataHandling/Operation/Fixtures/Sites')
        );

        GeneralUtility::setSingletonInstance(SiteConfiguration::class, $siteConfiguration);
    }

    /**
     * @test
     */
    public function creatingPageResultsInPageRecord(): void
    {
        $data = [
            'pid' => 'RootPage',
            'title' => 'INTEREST',
        ];

        $mappingRepository = new RemoteIdMappingRepository();

        (new CreateRecordOperation(
            new RecordRepresentation(
                $data,
                new RecordInstanceIdentifier(
                    'pages',
                    'Page-1'
                )
            )
        ))();

        $createdPageUid = $mappingRepository->get('Page-1');

        self::assertGreaterThan(0, $createdPageUid);

        $databaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT * FROM pages WHERE uid = ' . $createdPageUid)
            ->fetchAssociative();

        self::assertIsArray($databaseRow);

        self::assertSame($data['title'], $databaseRow['title']);
    }

    /**
     * @test
     */
    public function createOperationResultsInCorrectRecord()
    {
        $data = $this->recordRepresentationAndCorrespondingRowDataProvider();

        $originalName = $this->getName();

        foreach ($data as $key => $value) {
            $this->setName($originalName . ' (' . $key . ')');

            $this->createOperationResultsInCorrectRecordDataIteration(...$value);
        }
    }

    protected function createOperationResultsInCorrectRecordDataIteration(
        RecordRepresentation $recordRepresentation,
        array $expectedRow
    ) {
        $mappingRepository = new RemoteIdMappingRepository();

        (new CreateRecordOperation($recordRepresentation))();

        $queryFields = implode(',', array_keys($expectedRow));
        $table = $recordRepresentation->getRecordInstanceIdentifier()->getTable();
        $uid = $mappingRepository->get($recordRepresentation->getRecordInstanceIdentifier()->getRemoteIdWithAspects());

        $createdRecord = $this
            ->getConnectionPool()
            ->getConnectionForTable($table)
            ->executeQuery(
                'SELECT ' . $queryFields . ' FROM ' . $table . ' WHERE uid = ' . $uid
            )
            ->fetchAssociative();

        self::assertEquals($createdRecord, $expectedRow, 'Comparing created record with expected data.');
    }

    public function recordRepresentationAndCorrespondingRowDataProvider()
    {
        return [
            'Base language record' => [
                new RecordRepresentation(
                    [
                        'pid' => 'RootPage',
                        'header' => 'TEST',
                    ],
                    new RecordInstanceIdentifier(
                        'tt_content',
                        'ContentA',
                        ''
                    )
                ),
                [
                    'pid' => 1,
                    'header' => 'TEST',
                ],
            ],
            'Record with language' => [
                new RecordRepresentation(
                    [
                        'pid' => 'RootPage',
                        'header' => 'TEST',
                    ],
                    new RecordInstanceIdentifier(
                        'tt_content',
                        'ContentB',
                        'de'
                    )
                ),
                [
                    'pid' => 1,
                    'header' => 'TEST',
                    'sys_language_uid' => 1,
                ],
            ],
            'Translation of base language record' => [
                new RecordRepresentation(
                    [
                        'pid' => 'RootPage',
                        'header' => 'Translated TEST',
                    ],
                    new RecordInstanceIdentifier(
                        'tt_content',
                        'ContentElement',
                        'de'
                    )
                ),
                [
                    'pid' => 1,
                    'header' => 'Translated TEST',
                    'sys_language_uid' => 1,
                    'l18n_parent' => 297,
                ],
            ],

        ];
    }
}
