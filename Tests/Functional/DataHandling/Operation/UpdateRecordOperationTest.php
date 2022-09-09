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

        $mappingRepository->add('RootPage', 'pages', 1);

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
}
