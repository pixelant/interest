<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\ResolveStoragePidEventHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ResolveStoragePidEventHandlerTest extends UnitTestCase
{
    /**
     * @test
     */
    public function resolveStoragePidReturnsZeroIfRootLevelIsOne()
    {
        $tableName = 'testtable';

        $GLOBALS['TCA'][$tableName] = [
            'ctrl' => [
                'rootLevel' => 1,
            ],
            'columns' => [],
        ];

        $mockCreateRecordOperation = $this->createMock(CreateRecordOperation::class);

        $mockCreateRecordOperation
            ->method('getTable')
            ->willReturn($tableName);

        $mockCreateRecordOperation
            ->expects(self::once())
            ->method('setStoragePid')
            ->with(0);

        $event = new RecordOperationSetupEvent($mockCreateRecordOperation);

        (new ResolveStoragePidEventHandler())($event);
    }
}
