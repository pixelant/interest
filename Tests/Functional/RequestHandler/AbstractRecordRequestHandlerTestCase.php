<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;

use FriendsOfTYPO3\Interest\Domain\Repository\RemoteIdMappingRepository;
use FriendsOfTYPO3\Interest\Tests\Functional\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractRecordRequestHandlerTestCase extends FunctionalTestCase
{
    use SiteBasedTestTrait;

    private const BEARER_TOKEN = '0123456789abcdef0123456789abcdef';

    protected const LANGUAGE_PRESETS = [
        'en' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8'],
        'de' => ['id' => 1, 'title' => 'German', 'locale' => 'de_DE.UTF-8'],
        'es' => ['id' => 2, 'title' => 'Spanish', 'locale' => 'es_ES.UTF-8'],
        'fr' => ['id' => 3, 'title' => 'French', 'locale' => 'fr_FR.UTF-8'],
    ];

    protected RemoteIdMappingRepository $mappingRepository;

    protected array $testExtensionsToLoad = ['friendsoftypo3/interest'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mappingRepository = new RemoteIdMappingRepository();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendGroups.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Records.csv');

        $this->writeSiteConfiguration(
            'main',
            $this->buildSiteConfiguration(1, '/'),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
                $this->buildLanguageConfiguration('de', '/de/'),
            ]
        );

        $this
            ->getConnectionPool()
            ->getConnectionForTable('tx_interest_api_token')
            ->executeQuery('INSERT INTO tx_interest_api_token SET be_user = 1, token = \'' . self::BEARER_TOKEN . '\'');
    }

    public static function requestAuthenticationDataProvider(): array
    {
        return [
            'admin user with bearer token' => [
                'testPage1',
                'Bearer ' . self::BEARER_TOKEN,
            ],
            'admin user with password' => [
                'testPage2',
                'basic ' . base64_encode('admin:password'),
            ],
            'editor user with password' => [
                'testPage3',
                'basic ' . base64_encode('editor:password'),
            ],
        ];
    }
}
