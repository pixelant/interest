<?php

/**
 * @noinspection SqlNoDataSourceInspection
 * @noinspection SqlDialectInspection
 */

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;

class DeleteRecordOperationTest extends AbstractRecordOperationFunctionalTestCase
{
    /**
     * @test
     */
    public function deletingPageSetsDeletedField()
    {
        $mappingRepository = new RemoteIdMappingRepository();

        $mappingRepository->add('Dummy1234', 'pages', 4);

        (new DeleteRecordOperation(
            new RecordRepresentation(
                [],
                new RecordInstanceIdentifier(
                    'pages',
                    'Dummy1234'
                )
            )
        ))();

        $databaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT * FROM pages WHERE uid = 4')
            ->fetchAssociative();

        self::assertIsArray($databaseRow);

        self::assertSame(1, $databaseRow['deleted']);
    }

    /**
     * @test
     */
    public function deletingContentSetsDeletedField()
    {
        $mappingRepository = new RemoteIdMappingRepository();

        (new DeleteRecordOperation(
            new RecordRepresentation(
                [],
                new RecordInstanceIdentifier(
                    'tt_content',
                    'TranslatedContentElement'
                )
            )
        ))();

        $databaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->executeQuery('SELECT * FROM tt_content WHERE uid = 298')
            ->fetchAssociative();

        self::assertIsArray($databaseRow);

        self::assertSame(1, $databaseRow['deleted']);
    }

    /**
     * @test
     */
    public function deletingTranslationOfContentSetsDeletedField()
    {
        $mappingRepository = new RemoteIdMappingRepository();

        (new DeleteRecordOperation(
            new RecordRepresentation(
                [],
                new RecordInstanceIdentifier(
                    'tt_content',
                    'TranslatedContentElement',
                    'de'
                )
            )
        ))();

        $databaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->executeQuery('SELECT * FROM tt_content WHERE uid = 299')
            ->fetchAssociative();

        self::assertIsArray($databaseRow);

        self::assertSame(1, $databaseRow['deleted']);
    }
}
