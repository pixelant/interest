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
     * @dataProvider recordRepresentationAndCorrespondingRowDataProvider
     * @test
     */
    public function createOperationResultsInCorrectRecord(RecordRepresentation $recordRepresentation, array $expectedRow)
    {
        $mappingRepository = new RemoteIdMappingRepository();

        (new CreateRecordOperation($recordRepresentation))();

        $queryFields = implode(',', array_keys($expectedRow));
        $table = $recordRepresentation->getRecordInstanceIdentifier()->getTable();
        $uid = $mappingRepository->get($recordRepresentation->getRecordInstanceIdentifier()->getRemoteId());

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
                    'pid' => 0,
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
                        'german'
                    )
                ),
                [
                    'pid' => 0,
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
                        'german'
                    )
                ),
                [
                    'pid' => 0,
                    'header' => 'Translated TEST',
                    'sys_language_uid' => 1,
                    'l18n_parent' => 297,
                ],
            ],

        ];
    }
}
