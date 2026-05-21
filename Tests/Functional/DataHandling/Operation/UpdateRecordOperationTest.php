<?php

/**
 * @noinspection SqlNoDataSourceInspection
 * @noinspection SqlDialectInspection
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\DataHandling\Operation;

use FriendsOfTYPO3\Interest\DataHandling\Operation\CreateRecordOperation;
use FriendsOfTYPO3\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use FriendsOfTYPO3\Interest\DataHandling\Operation\UpdateRecordOperation;
use FriendsOfTYPO3\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use FriendsOfTYPO3\Interest\Domain\Model\Dto\RecordRepresentation;
use FriendsOfTYPO3\Interest\Domain\Repository\RemoteIdMappingRepository;
use PHPUnit\Framework\Attributes\Test;

class UpdateRecordOperationTest extends AbstractRecordOperationFunctionalTestCase
{
    protected array $testExtensionsToLoad = ['friendsoftypo3/interest'];

    #[Test]
    public function updatingPageChangesFields(): void
    {
        $data = [
            'title' => 'INTEREST',
        ];

        (new UpdateRecordOperation(
            new RecordRepresentation(
                $data,
                new RecordInstanceIdentifier(
                    'pages',
                    'RootPage'
                )
            )
        ))();

        $databaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT * FROM pages WHERE uid = 1')
            ->fetchAssociative();

        self::assertIsArray($databaseRow);

        self::assertEquals($data['title'], $databaseRow['title']);
    }

    #[Test]
    public function updateOperationResultsInCorrectRecord(): void
    {
        $data = $this->recordRepresentationAndCorrespondingRowDataProvider();

        foreach ($data as $key => $value) {
            $this->updateOperationResultsInCorrectRecordDataIteration(...$value);
        }
    }

    #[Test]
    public function updatingForeignFieldRemovesNonExistingRelationsAndUseCorrectSorting(): void
    {
        $mappingRepository = new RemoteIdMappingRepository();

        $contentElementRemoteIdentifier = 'MediaContentElement_Sample';

        $sysFilesRemoteIdentifiers = [
            'MediaElementSysFile_1',
            'MediaElementSysFile_2',
            'MediaElementSysFile_3',
            'MediaElementSysFile_4',
            'MediaElementSysFile_5',
            'MediaElementSysFile_6',
        ];

        $sysFilesUidRemoteIdentifierMappings = [];

        $this->createMediaContentElement($contentElementRemoteIdentifier);
        $contentUid = $mappingRepository->get('MediaContentElement_Sample');

        $this->createSysFiles($sysFilesRemoteIdentifiers);
        foreach ($sysFilesRemoteIdentifiers as $sysFilesRemoteIdentifier) {
            $uid = $mappingRepository->get($sysFilesRemoteIdentifier);
            $sysFilesUidRemoteIdentifierMappings[$uid] = $sysFilesRemoteIdentifier;
        }

        $imageUpdates = [
            0 => [
                'MediaElementSysFile_5',
                'MediaElementSysFile_3',
                'MediaElementSysFile_1',
            ],
            1 => [
                'MediaElementSysFile_1',
                'MediaElementSysFile_3',
                'MediaElementSysFile_5',
            ],
            2 => [
                'MediaElementSysFile_1',
                'MediaElementSysFile_2',
                'MediaElementSysFile_5',
                'MediaElementSysFile_3',
            ],
            3 => [
                'MediaElementSysFile_6',
                'MediaElementSysFile_2',
                'MediaElementSysFile_5',
            ],
            4 => [],
            5 => [
                'MediaElementSysFile_4',
            ],
        ];

        foreach ($imageUpdates as $imageUpdateRemoteIdentifiers) {
            $this->updateMediaContentElementImages('MediaContentElement_Sample', $imageUpdateRemoteIdentifiers);

            $query = 'SELECT uid_local FROM sys_file_reference WHERE uid_foreign = ' . $contentUid;
            $query .= ' AND tablenames = \'tt_content\' AND fieldname = \'image\' AND deleted = 0';
            $query .= ' ORDER BY sorting_foreign;';

            $imageSysFileReferences = $this
                ->getConnectionPool()
                ->getConnectionForTable('sys_file_reference')
                ->executeQuery($query)
                ->fetchAllAssociative();

            $databaseImageIds = array_column($imageSysFileReferences, 'uid_local');

            $expectedImageIds = [];
            foreach ($imageUpdateRemoteIdentifiers as $sfrRemoteIdentifier) {
                $expectedImageIds[] = $mappingRepository->get($sfrRemoteIdentifier);
            }

            self::assertEquals(
                $expectedImageIds,
                $databaseImageIds,
                'Images attached to media content element isn\'t as expected.'
            );
        }
    }

    protected function createMediaContentElement(string $remoteIdentifier)
    {
        $mappingRepository = new RemoteIdMappingRepository();

        (new CreateRecordOperation(
            new RecordRepresentation(
                [
                    'pid' => 'RootPage',
                    'CType' => 'textpic',
                ],
                new RecordInstanceIdentifier(
                    'tt_content',
                    $remoteIdentifier
                )
            )
        ))();

        self::assertNotEquals(
            0,
            $mappingRepository->get($remoteIdentifier),
            'Could not find content with remote identifier: ' . $remoteIdentifier
        );
    }

    protected function updateMediaContentElementImages(
        string $contentElementRemoteIdentifier,
        array $sysFilesRemoteIdentifiers
    ) {
        $mappingRepository = new RemoteIdMappingRepository();
        $sysFileReferenceIdentifiers = [];

        foreach ($sysFilesRemoteIdentifiers as $sysFilesRemoteIdentifier) {
            $sfrRemoteIdentifier = $contentElementRemoteIdentifier . '_' . $sysFilesRemoteIdentifier;
            if ($mappingRepository->get($sfrRemoteIdentifier) === 0) {
                $recordRepresentationData = [
                    'pid' => 'RootPage',
                    'uid_local' => $sysFilesRemoteIdentifier,
                    'uid_foreign' => $contentElementRemoteIdentifier,
                    'fieldname' => 'image',
                ];

                (new CreateRecordOperation(
                    new RecordRepresentation(
                        $recordRepresentationData,
                        new RecordInstanceIdentifier(
                            'sys_file_reference',
                            $sfrRemoteIdentifier
                        )
                    )
                ))();
            }
            $sysFileReferenceIdentifiers[] = $sfrRemoteIdentifier;
        }

        (new UpdateRecordOperation(
            new RecordRepresentation(
                [
                    'image' => implode(',', $sysFileReferenceIdentifiers),
                ],
                new RecordInstanceIdentifier(
                    'tt_content',
                    $contentElementRemoteIdentifier
                )
            )
        ))();
    }

    protected function createSysFiles(array $remoteIdentifiers)
    {
        $mappingRepository = new RemoteIdMappingRepository();
        $fileData = base64_encode(file_get_contents(__DIR__ . '/../../Fixtures/Image.jpg'));

        $createSysFile = function (string $remoteId) use ($fileData) {
            (new CreateRecordOperation(
                new RecordRepresentation(
                    [
                        'fileData' => $fileData,
                        'name' => 'image_' . $remoteId . '.jpg',
                    ],
                    new RecordInstanceIdentifier(
                        'sys_file',
                        $remoteId
                    )
                )
            ))();
        };

        foreach ($remoteIdentifiers as $remoteIdentifier) {
            try {
                $createSysFile((string)$remoteIdentifier);
            } catch (StopRecordOperationException) {
                continue;
            }

            self::assertNotEquals(
                0,
                $mappingRepository->get($remoteIdentifier),
                'Could not find file with remote identifier: ' . $remoteIdentifier
            );
        }
    }

    protected function updateOperationResultsInCorrectRecordDataIteration(
        RecordRepresentation $recordRepresentation,
        array $expectedRow
    ) {
        $mappingRepository = new RemoteIdMappingRepository();

        (new UpdateRecordOperation($recordRepresentation))();

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

        self::assertEquals($expectedRow, $createdRecord, 'Comparing created record with expected data.');
    }

    public function recordRepresentationAndCorrespondingRowDataProvider()
    {
        return [
            'Base language record' => [
                new RecordRepresentation(
                    [
                        'bodytext' => 'base language text',
                    ],
                    new RecordInstanceIdentifier(
                        'tt_content',
                        'TranslatedContentElement',
                        ''
                    )
                ),
                [
                    'pid' => 1,
                    'uid' => 298,
                    'bodytext' => 'base language text',
                ],
            ],
            'Translated record' => [
                new RecordRepresentation(
                    [
                        'bodytext' => 'translated text',
                    ],
                    new RecordInstanceIdentifier(
                        'tt_content',
                        'TranslatedContentElement',
                        'de'
                    )
                ),
                [
                    'pid' => 1,
                    'uid' => 299,
                    'bodytext' => 'translated text',
                ],
            ],
            'Remove one of multiple relations' => [
                new RecordRepresentation(
                    [
                        'records' => 'TranslatedContentElement',
                    ],
                    new RecordInstanceIdentifier(
                        'tt_content',
                        'MultipleReferences',
                        ''
                    )
                ),
                [
                    'records' => '298',
                ],
            ],
            'Add one of multiple relations first' => [
                new RecordRepresentation(
                    [
                        'records' => 'ContentElement2,TranslatedContentElement',
                    ],
                    new RecordInstanceIdentifier(
                        'tt_content',
                        'MultipleReferences',
                        ''
                    )
                ),
                [
                    'records' => '296,298',
                ],
            ],
        ];
    }
}
