<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Functional\Domain\Repository;

use Pixelant\Interest\Domain\Repository\TokenRepository;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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

        $this->setUpBackendUserFromFixture(1);

        $this->importDataSet('PACKAGE:typo3/testing-framework/Resources/Core/Functional/Fixtures/pages.xml');

        $this->setUpFrontendRootPage(1);

        GeneralUtility::setIndpEnv('TYPO3_REQUEST_URL', 'http://www.example.com/');

        $languageServiceMock = $this->createMock(LanguageService::class);

        $languageServiceMock
            ->method('sL')
            ->willReturnArgument(0);

        $GLOBALS['LANG'] = $languageServiceMock;

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
