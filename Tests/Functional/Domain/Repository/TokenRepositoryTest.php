<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\Domain\Repository;

use Pixelant\Interest\Domain\Repository\TokenRepository;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class TokenRepositoryTest extends FunctionalTestCase
{
    /**
     * @var array<int, non-empty-string>
     */
    protected $testExtensionsToLoad = ['typo3conf/ext/interest'];

    /**
     * @var TokenRepository
     */
    protected $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new TokenRepository();
    }

    /**
     * @test
     */
    public function createTokenReturnsHashWithExpiryGreaterThanCreationDate()
    {
        $token = $this->subject->createTokenForBackendUser(1);

        self::assertIsString($token, 'Token is a string.');
        self::assertRegExp('/^[0-9a-f]{32}$/', $token, 'Token is a 32-character hexademical string.');

        $databaseRow = $this
            ->getConnectionPool()
            ->getConnectionForTable(TokenRepository::TABLE_NAME)
            ->executeQuery('SELECT * FROM ' . TokenRepository::TABLE_NAME)
            ->fetchAssociative();

        self::assertNotEquals(
            $databaseRow['crdate'],
            $databaseRow['expiry'],
            'Expiry is not creation date.'
        );

        self::assertNotEquals(
            0,
            $databaseRow['expiry'],
            'Expiry is not zero.'
        );

        self::assertGreaterThan(
            $databaseRow['crdate'],
            $databaseRow['expiry'],
            'Expiry is greater than creation date.'
        );

        $returnedUserId = $this->subject->findBackendUserIdByToken($token);

        self::assertIsInt(
            $returnedUserId,
            'Returned user ID is integer.'
        );

        self::assertEquals(
            1,
            $returnedUserId,
            'Correct user ID is returned for token.'
        );
    }
}
