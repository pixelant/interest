<?php /** @noinspection SqlNoDataSourceInspection */
/** @noinspection SqlDialectInspection */

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class CreateRecordOperationTest extends FunctionalTestCase
{
    /**
     * @var array<int, non-empty-string>
     */
    protected $testExtensionsToLoad = ['typo3conf/ext/interest'];

    /**
     * @var RemoteIdMappingRepository
     */
    protected RemoteIdMappingRepository $mappingRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $this->setUpBackendUserFromFixture(1);

        $this->importDataSet('PACKAGE:typo3/testing-framework/Resources/Core/Functional/Fixtures/pages.xml');

        $this->setUpFrontendRootPage(1);

        GeneralUtility::setIndpEnv('TYPO3_REQUEST_URL', 'http://www.example.com/');

        $languageServiceMock = $this->createMock(LanguageService::class);

        $languageServiceMock
            ->method('sL')
            ->willReturnArgument(0);

        $GLOBALS['LANG'] = $languageServiceMock;
    }

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

        $contentObjectRenderer = new ContentObjectRenderer();

        (new CreateRecordOperation(
            $data,
            'pages',
            'Page-1',
            null,
            null,
            [],
            $contentObjectRenderer
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
