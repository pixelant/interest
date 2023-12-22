<?php

/**
 * @noinspection SqlNoDataSourceInspection
 * @noinspection SqlDialectInspection
 */

declare(strict_types=1);

namespace Functional\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\CopyRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Tests\Functional\DataHandling\Operation\AbstractRecordOperationFunctionalTestCase;

class CopyRecordOperationTest extends AbstractRecordOperationFunctionalTestCase
{
    /**
     * @test
     */
    public function copyingPageCreatesCopyAndAssignsRemoteId()
    {
        $resultingRemoteId = 'resultingRemoteId';

        $mappingRepository = new RemoteIdMappingRepository();

        $mappingRepository->add('Dummy1234', 'pages', 4);

        (new CopyRecordOperation(
            new RecordRepresentation(
                [],
                new RecordInstanceIdentifier(
                    'pages',
                    'Dummy1234'
                )
            ),
            $resultingRemoteId
        ))();

        self::assertTrue($mappingRepository->exists('resultingRemoteId'));

        $resultingUid = $mappingRepository->get($resultingRemoteId);

        self::assertNotEquals(0, $resultingUid, 'The resulting UID is nonzero.');

        $resultingDatabaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT * FROM pages WHERE uid = ' . $resultingUid)
            ->fetchAssociative();

        self::assertEquals(
            4,
            $resultingDatabaseRow['t3_origuid'],
            'New record is a copy of original according to t3_origuid field'
        );

        self::assertStringContainsString(
            'Dummy 1-2',
            $resultingDatabaseRow['title'],
            'Title of copy result contains original title.'
        );
    }
}
