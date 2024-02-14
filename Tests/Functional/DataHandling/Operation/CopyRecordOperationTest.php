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
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\StringUtility;

class CopyRecordOperationTest extends AbstractRecordOperationFunctionalTestCase
{
    /**
     * Creates a mocked LanguageService that returns a random string ending in "%s". We need the "%s" to avoid an error
     * when prefixing a record title when copying.
     */
    protected function initializeLanguageService()
    {
        $languageServiceMock = $this->createMock(LanguageService::class);

        $languageServiceMock
            ->method('sL')
            ->willReturn(StringUtility::getUniqueId() . '%s');

        $GLOBALS['LANG'] = $languageServiceMock;
    }

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

        $originalDatabaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT * FROM pages WHERE uid = 4')
            ->fetchAssociative();

        $resultingDatabaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT * FROM pages WHERE uid = ' . $resultingUid)
            ->fetchAssociative();

        self::assertNotEmpty($originalDatabaseRow['title'], 'Original title is not empty');

        self::assertEquals(
            $originalDatabaseRow['uid'],
            $resultingDatabaseRow['t3_origuid'],
            'New record is a copy of original according to t3_origuid field'
        );

        self::assertStringContainsString(
            $originalDatabaseRow['title'],
            $resultingDatabaseRow['title'],
            'Title of copy result contains original title.'
        );
    }
}
