<?php

/** @noinspection SqlNoDataSourceInspection */
/** @noinspection SqlDialectInspection */

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;

class CreateRecordOperationTest extends AbstractRecordOperationFunctionalTestCase
{
    /**
     * @test
     */
    public function creatingPageResultsInPageRecord(): void
    {
        $data = [
            'pid' => 'ParentPage',
            'title' => 'INTEREST',
        ];

        $mappingRepository = new RemoteIdMappingRepository();

        $mappingRepository->add('ParentPage', 'pages', 1);

        (new CreateRecordOperation(
            $data,
            'pages',
            'Page-1'
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
}
