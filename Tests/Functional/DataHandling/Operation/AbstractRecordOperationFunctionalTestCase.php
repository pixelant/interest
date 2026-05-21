<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\DataHandling\Operation;

use FriendsOfTYPO3\Interest\Tests\Functional\SiteBasedTestTrait;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractRecordOperationFunctionalTestCase extends FunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8'],
        'de' => ['id' => 1, 'title' => 'German', 'locale' => 'de_DE.UTF-8'],
        'es' => ['id' => 2, 'title' => 'Spanish', 'locale' => 'es_ES.UTF-8'],
        'fr' => ['id' => 3, 'title' => 'French', 'locale' => 'fr_FR.UTF-8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/BackendUsers.csv');

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Records.csv');

        $this->writeSiteConfiguration(
            'main',
            $this->buildSiteConfiguration(1, '/'),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
                $this->buildLanguageConfiguration('de', '/de/'),
                $this->buildLanguageConfiguration('es', '/es/'),
                $this->buildLanguageConfiguration('fr', '/fr/'),
            ]
        );

        $this->setUpBackendUser(1);

        $this->setUpFrontendRootPage(1);

        GeneralUtility::setIndpEnv('TYPO3_REQUEST_URL', 'http://www.example.com/');

        $languageServiceMock = $this->createMock(LanguageService::class);

        $languageServiceMock
            ->method('sL')
            ->willReturnArgument(0);

        $GLOBALS['LANG'] = $languageServiceMock;
    }
}
