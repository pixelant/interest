<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

trait UpdateRequestTestTrait
{
    #[Test]
    #[DataProvider('requestAuthenticationDataProvider')]
    public function updateRequestWithAuthentication(string $remoteId, string $authorizationValue): void
    {
        $recordTitle = 'Test Name ' . $remoteId . ' ' . bin2hex(random_bytes(16));
        $remoteId = 'Dummy1234Page';

        $request = (new InternalRequest('http://localhost/rest/pages/Dummy1234Page'))
            ->withMethod(self::REQUEST_METHOD)
            ->withHeader('Authorization', $authorizationValue)
            ->withBody((new StreamFactory())->createStream('{"data":{"title":"' . $recordTitle . '"}}'));

        $response = $this->executeFrontendSubRequest($request);

        self::assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getBody()->getContents(), true);

        self::assertIsBool(
            $responseData['success'],
            'Response contains boolean success property'
        );
        self::assertTrue(
            $responseData['success'],
            'Response contains boolean success property with value true'
        );
        self::assertIsString(
            $responseData['message'],
            'Response contains message property with string value'
        );
        self::assertNotEmpty(
            $responseData['message'],
            'Response contains message property with non-empty string value'
        );

        $createdPageUid = $this->mappingRepository->get($remoteId);

        self::assertGreaterThan(
            0,
            $createdPageUid,
            'Remote ID "' . $remoteId . '" has UID greater than zero'
        );

        $databaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable('pages')
            ->executeQuery('SELECT * FROM pages WHERE uid = ' . $createdPageUid)
            ->fetchAssociative();

        self::assertEquals(
            $recordTitle,
            $databaseRow['title'],
            'Database row for remote ID "' . $remoteId . '" has correct title'
        );
    }
}
