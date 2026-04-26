<?php

declare(strict_types=1);


namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;


use FriendsOfTYPO3\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractRecordRequestHandlerTestCase extends FunctionalTestCase
{
    private const BEARER_TOKEN = '0123456789abcdef0123456789abcdef';

    protected RemoteIdMappingRepository $mappingRepository;

    protected array $testExtensionsToLoad = ['friendsoftypo3/interest'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUser.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Records.csv');

        $this->mappingRepository = new RemoteIdMappingRepository();

        // Add only once.
        if (!$this->mappingRepository->exists('Dummy1234')) {
            $this->mappingRepository->add('Dummy1234', 'pages', 4);
        }

        $this
            ->getConnectionPool()
            ->getConnectionForTable('tx_interest_api_token')
            ->executeQuery('INSERT INTO tx_interest_api_token SET be_user = 1, token = \'' . self::BEARER_TOKEN . '\'');
    }

    public static function requestAuthenticationDataProvider(): array
    {
        return [
            [
                'testPage1',
                'Bearer ' . self::BEARER_TOKEN,
            ],
            [
                'testPage2',
                'basic ' . base64_encode('admin:password')
            ]
        ];
    }
}
