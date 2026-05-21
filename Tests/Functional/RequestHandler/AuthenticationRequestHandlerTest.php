<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Interest\Tests\Functional\RequestHandler;

use FriendsOfTYPO3\Interest\Domain\Repository\TokenRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class AuthenticationRequestHandlerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['friendsoftypo3/interest'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Records.csv');
    }

    #[Test]
    public function authenticationRequestWithoutAuthorizationHeaderFails()
    {
        $request = (new InternalRequest('http://localhost/rest/authenticate'))
            ->withMethod('POST');

        $response = $this->executeFrontendSubRequest($request);

        self::assertEquals(
            401,
            $response->getStatusCode(),
            'Correct response code for failed authentication'
        );

        $responseData = json_decode($response->getBody()->getContents(), true);

        self::assertIsBool(
            $responseData['success'],
            'Response contains boolean success property'
        );
        self::assertFalse(
            $responseData['success'],
            'Response contains boolean success property with value false'
        );
        self::assertArrayHasKey(
            'message',
            $responseData,
            'Response contains message property'
        );
        self::assertIsString(
            $responseData['message'],
            'Response contains message property with string value'
        );
    }

    #[Test]
    #[DataProvider('requestWithDifferentMethodDataProvider')]
    public function requestWithDifferentMethodFails(string $method)
    {
        $request = (new InternalRequest('http://localhost/rest/authenticate'))
            ->withMethod($method)
            ->withHeader('Authorization', 'basic ' . base64_encode('admin:password'));

        $response = $this->executeFrontendSubRequest($request);

        self::assertEquals(
            401,
            $response->getStatusCode(),
            'Correct response code for failed authentication'
        );

        $responseData = json_decode($response->getBody()->getContents(), true);

        self::assertIsBool(
            $responseData['success'],
            'Response contains boolean success property'
        );
        self::assertFalse(
            $responseData['success'],
            'Response contains boolean success property with value false'
        );
        self::assertArrayHasKey(
            'message',
            $responseData,
            'Response contains message property'
        );
        self::assertIsString(
            $responseData['message'],
            'Response contains message property with string value'
        );
    }

    public static function requestWithDifferentMethodDataProvider(): array
    {
        return [
            ['GET'],
            ['PUT'],
            ['PATCH'],
            ['DELETE'],
            ['HEAD'],
            ['OPTIONS'],
        ];
    }

    #[Test]
    public function failedAuthenticationRequest()
    {
        $request = (new InternalRequest('http://localhost/rest/authenticate'))
            ->withMethod('POST')
            ->withHeader('Authorization', 'basic ' . base64_encode('incorrect:secret'));

        $response = $this->executeFrontendSubRequest($request);

        self::assertEquals(
            401,
            $response->getStatusCode(),
            'Correct response code for failed authentication'
        );

        $responseData = json_decode($response->getBody()->getContents(), true);

        self::assertIsBool(
            $responseData['success'],
            'Response contains boolean success property'
        );
        self::assertFalse(
            $responseData['success'],
            'Response contains boolean success property with value false'
        );
        self::assertArrayHasKey(
            'message',
            $responseData,
            'Response contains message property'
        );
        self::assertIsString(
            $responseData['message'],
            'Response contains message property with string value'
        );
    }

    #[Test]
    #[DataProvider('successfulAuthenticationRequestDataProvider')]
    public function successfulAuthenticationRequest(string $encodedUsernameAndPassword, int $userId): void
    {
        $request = (new InternalRequest('http://localhost/rest/authenticate'))
            ->withMethod('POST')
            ->withHeader('Authorization', 'basic ' . $encodedUsernameAndPassword);

        $response = $this->executeFrontendSubRequest($request);

        self::assertEquals(200, $response->getStatusCode(), 'Successful authentication response code');

        $responseData = json_decode($response->getBody()->getContents(), true);

        self::assertIsBool(
            $responseData['success'],
            'Authentication response contains boolean success property'
        );
        self::assertTrue(
            $responseData['success'],
            'Authentication response contains boolean success property with value true'
        );
        self::assertArrayHasKey(
            'token',
            $responseData,
            'Authentication response contains token property'
        );
        self::assertIsString(
            $responseData['token'],
            'Authentication response contains token property with string value'
        );
        self::assertEquals(
            32,
            strlen($responseData['token']),
            'Authentication response contains token property with string value of length 40'
        );

        $tokenRepository = new TokenRepository();

        self::assertEquals(
            $userId,
            $tokenRepository->findBackendUserIdByToken($responseData['token']),
            'Token is valid for correct backend user'
        );
    }

    public static function successfulAuthenticationRequestDataProvider(): array
    {
        return [
            'admin user' => [
                base64_encode('admin:password'),
                1,
            ],
            'editor user' => [
                base64_encode('editor:password'),
                2,
            ],
        ];
    }
}
