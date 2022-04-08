<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
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
    }

    /**
     * @test
     */
    public function creatingPageResultsInPageRecord(): void
    {
        $data = [
            'pid' => 'ParentPage',
            'title' => 'INTEREST'
        ];

        $remoteIdMappingRepositoryMock = $this->createMock(RemoteIdMappingRepository::class);

        $remoteIdMappingRepositoryMock
            ->method('exists')
            ->with('ParentPage')
            ->willReturn(true);

        $remoteIdMappingRepositoryMock
            ->method('exists')
            ->with('Page-1')
            ->willReturn(false);

        GeneralUtility::addInstance(RemoteIdMappingRepository::class, $remoteIdMappingRepositoryMock);

        $contentObjectRendererMock = $this->createMock(ContentObjectRenderer::class);

        $operation = new CreateRecordOperation(
            $data,
            'pages',
            'Page-1',
            null,
            null,
            [],
            $contentObjectRendererMock
        );

        $operation();
    }
}
