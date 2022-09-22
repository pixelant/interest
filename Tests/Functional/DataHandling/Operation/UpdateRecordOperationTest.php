<?php

/**
 * @noinspection SqlNoDataSourceInspection
 * @noinspection SqlDialectInspection
 */

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;

class UpdateRecordOperationTest extends AbstractRecordOperationFunctionalTestCase
{
    /**
     * @test
     */
    public function updatingPageChangesFields()
    {
        $data = [
            'title' => 'INTEREST',
        ];

        $mappingRepository = new RemoteIdMappingRepository();

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

        self::assertSame($data['title'], $databaseRow['title']);
    }

    /**
     * @test
     */
    public function updateOperationResultsInCorrectRecord()
    {
        $data = $this->recordRepresentationAndCorrespondingRowDataProvider();

        $originalName = $this->getName();

        foreach ($data as $key => $value) {
            $this->setName($originalName . ' (' . $key . ')');

            $this->updateOperationResultsInCorrectRecordDataIteration(...$value);
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

        self::assertEquals($createdRecord, $expectedRow, 'Comparing created record with expected data.');
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
