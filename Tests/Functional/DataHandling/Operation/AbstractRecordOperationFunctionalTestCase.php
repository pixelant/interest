<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\DataHandling\Operation;

use Pixelant\Interest\Utility\CompatibilityUtility;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractRecordOperationFunctionalTestCase extends FunctionalTestCase
{
    /**
     * @var array<non-empty-string>
     */
    protected array $testExtensionsToLoad = ['typo3conf/ext/interest'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/BackendUser.csv');

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Records.csv');

        $this->setUpBackendUser(1);

        $this->setUpFrontendRootPage(1);

        GeneralUtility::setIndpEnv('TYPO3_REQUEST_URL', 'http://www.example.com/');

        if (CompatibilityUtility::typo3VersionIsLessThan('12.0')) {
            $siteConfiguration = GeneralUtility::makeInstance(
                SiteConfiguration::class,
                GeneralUtility::getFileAbsFileName(
                    'EXT:interest/Tests/Functional/DataHandling/Operation/Fixtures/Sites'
                ),
            );
        } else {
            $siteConfiguration = GeneralUtility::makeInstance(
                SiteConfiguration::class,
                GeneralUtility::getFileAbsFileName(
                    'EXT:interest/Tests/Functional/DataHandling/Operation/Fixtures/Sites'
                ),
                GeneralUtility::makeInstance(EventDispatcher::class)
            );
        }

        GeneralUtility::setSingletonInstance(SiteConfiguration::class, $siteConfiguration);

        $languageServiceMock = $this->createMock(LanguageService::class);

        $languageServiceMock
            ->method('sL')
            ->willReturnArgument(0);

        $GLOBALS['LANG'] = $languageServiceMock;
    }
}
