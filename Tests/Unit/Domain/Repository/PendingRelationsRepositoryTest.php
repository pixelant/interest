<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\Domain\Repository;

use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use Pixelant\Interest\Utility\CompatibilityUtility;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class PendingRelationsRepositoryTest extends UnitTestCase
{
    /**
     * @test
     */
    public function emptyRemoteIdsAreIgnored(): void
    {
        if (CompatibilityUtility::typo3VersionIsLessThan('10')) {
            $subject = $this->getMockBuilder(PendingRelationsRepository::class)
                ->setMethods(['getQueryBuilder', 'setSingle'])
                ->getMock();
        } else {
            $subject = $this->getMockBuilder(PendingRelationsRepository::class)
                ->onlyMethods(['getQueryBuilder', 'setSingle'])
                ->getMock();
        }

        $subject->method('getQueryBuilder')->willReturn(
            $this->createMock(QueryBuilder::class)
        );

        $subject
            ->expects(self::exactly(1))
            ->method('setSingle')
            ->with('table1', 'field1', 1, 'remoteId1');

        $subject->set('table1', 'field1', 1, ['remoteId1', '', '']);
    }
}
