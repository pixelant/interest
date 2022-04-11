<?php /** @noinspection SqlNoDataSourceInspection */
/** @noinspection SqlDialectInspection */
/** @noinspection SqlDialectInspection */

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
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

        (new DeleteRecordOperation('Dummy1234'))();

        $databaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT * FROM pages WHERE uid = 4')
            ->fetchAssociative();

        self::assertIsArray($databaseRow);

        self::assertSame(1, $databaseRow['deleted']);
    }
}
