<?php

/**
 * @noinspection SqlNoDataSourceInspection
 * @noinspection SqlDialectInspection
 */

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CreateRecordOperationTest extends AbstractRecordOperationFunctionalTestCase
{
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

    public function recordRepresentationAndCorrespondingRowDataProvider(): array
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
            'Relation to multiple records' => [
                new RecordRepresentation(
                    [
                        'pid' => 'RootPage',
                        'CType' => 'shortcut',
                        'records' => 'ContentElement,TranslatedContentElement',
                    ],
                    new RecordInstanceIdentifier(
                        'tt_content',
                        'ReferenceContentElement',
                        ''
                    )
                ),
                [
                    'CType' => 'shortcut',
                    'records' => '297,298',
                ],
            ],
        ];
    }

    /**
     * @test
     */
    public function createAdvancedInlineMmRelationsInDifferentOrder()
    {
        $fileData = base64_encode(file_get_contents(__DIR__ . '/Fixtures/Image.jpg'));

        $createContentElement = function (string $iteration) {
            (new CreateRecordOperation(
                new RecordRepresentation(
                    [
                        'pid' => 'RootPage',
                        'CType' => 'textpic',
                        'image' => 'MediaElementSysFileReference_' . $iteration,
                    ],
                    new RecordInstanceIdentifier(
                        'tt_content',
                        'MediaContentElement_' . $iteration
                    )
                )
            ))();
        };

        $createSysFileReference = function (string $iteration) {
            (new CreateRecordOperation(
                new RecordRepresentation(
                    [
                        'pid' => 'RootPage',
                        'uid_local' => 'MediaElementSysFile_' . $iteration,
                        'table_local' => 'sys_file',
                        'uid_foreign' => 'MediaContentElement_' . $iteration,
                        'fieldname' => 'image',
                    ],
                    new RecordInstanceIdentifier(
                        'sys_file_reference',
                        'MediaElementSysFileReference_' . $iteration
                    )
                )
            ))();
        };

        $createSysFile = function (string $iteration) use ($fileData) {
            (new CreateRecordOperation(
                new RecordRepresentation(
                    [
                        'fileData' => $fileData,
                        'name' => 'image_' . $iteration . '.jpg',
                    ],
                    new RecordInstanceIdentifier(
                        'sys_file',
                        'MediaElementSysFile_' . $iteration
                    )
                )
            ))();
        };

        $combinations = [
            'a' => [
                $createContentElement,
                $createSysFileReference,
                $createSysFile,
            ],
            'b' => [
                $createSysFile,
                $createContentElement,
                $createSysFileReference,
            ],
            'c' => [
                $createSysFileReference,
                $createSysFile,
                $createContentElement,
            ],
            'd' => [
                $createSysFileReference,
                $createContentElement,
                $createSysFile,
            ],
            'e' => [
                $createContentElement,
                $createSysFile,
                $createSysFileReference,
            ],
            'f' => [
                $createSysFile,
                $createSysFileReference,
                $createContentElement,
            ],
        ];

        $mappingRepository = new RemoteIdMappingRepository();

        foreach ($combinations as $iteration => $functions) {
            foreach ($functions as $function) {
                try {
                    $function($iteration);
                } catch (StopRecordOperationException $e) {
                    continue;
                }
            }

            self::assertNotEquals(
                0,
                $mappingRepository->get('MediaContentElement_' . $iteration),
                'MediaContentElement_' . $iteration . ' is not zero'
            );

            self::assertNotEquals(
                0,
                $mappingRepository->get('MediaElementSysFileReference_' . $iteration),
                'MediaElementSysFileReference_' . $iteration . ' is not zero'
            );

            self::assertNotEquals(
                0,
                $mappingRepository->get('MediaElementSysFile_' . $iteration),
                'MediaElementSysFile_' . $iteration . ' is not zero'
            );

            $createdContentElement = $this
                ->getConnectionPool()
                ->getConnectionForTable('tt_content')
                ->executeQuery(
                    'SELECT image FROM tt_content WHERE uid = '
                    . $mappingRepository->get('MediaContentElement_' . $iteration)
                )
                ->fetchAssociative();

            self::assertEquals(
                [
                    'image' => 1,
                ],
                $createdContentElement,
                'Created content element iteration ' . $iteration
            );

            $createdSysFileReference = $this
                ->getConnectionPool()
                ->getConnectionForTable('tt_content')
                ->executeQuery(
                    'SELECT uid_local, table_local, uid_foreign, fieldname FROM sys_file_reference WHERE uid = '
                    . $mappingRepository->get('MediaElementSysFileReference_' . $iteration)
                )
                ->fetchAssociative();

            self::assertEquals(
                [
                    'uid_local' => $mappingRepository->get('MediaElementSysFile_' . $iteration),
                    'table_local' => 'sys_file',
                    'uid_foreign' => $mappingRepository->get('MediaContentElement_' . $iteration),
                    'fieldname' => 'image',
                ],
                $createdSysFileReference,
                'Created sys_file_reference iteration ' . $iteration
            );

            $createdSysFile = $this
                ->getConnectionPool()
                ->getConnectionForTable('tt_content')
                ->executeQuery(
                    'SELECT name FROM sys_file WHERE uid = '
                    . $mappingRepository->get('MediaElementSysFile_' . $iteration)
                )
                ->fetchAssociative();

            self::assertEquals(
                [
                    'name' => 'image_' . $iteration . '.jpg',
                ],
                $createdSysFile,
                'Created sys_file iteration ' . $iteration
            );
        }
    }

    /**
     * @test
     */
    public function createEmptyFileIsHandledAsConfigured()
    {
        $createEmptySysFile = function () {
            (new CreateRecordOperation(
                new RecordRepresentation(
                    [
                        'fileData' => '',
                        'name' => 'emptyFile.txt',
                    ],
                    new RecordInstanceIdentifier(
                        'sys_file',
                        'EmptyFile'
                    )
                )
            ))();
        };

        GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->set('interest', ['handleEmptyFile' => '1']);

        self::expectException(StopRecordOperationException::class);
        self::expectExceptionCode(1692921622763);

        $createEmptySysFile();

        GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->set('interest', ['handleEmptyFile' => '2']);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionCode(1692921660432);

        $createEmptySysFile();

        GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->set('interest', ['handleEmptyFile' => '0']);

        $createEmptySysFile();

        $mappingRepository = new RemoteIdMappingRepository();

        $fileId = $mappingRepository->get('EmptyFile');

        $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($fileId);

        self::assertIsObject($file, 'File object was found');
        self::assertEquals(0, $file->getSize(), 'File size is zero');
        self::assertEmpty($file->getSize(), 'File content is empty');
    }
}
