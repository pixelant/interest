<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\SetPid;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class SetPidTest extends UnitTestCase
{
    /**
     * @test
     */
    public function setsPidOnCreateOperationIfNotAlreadySet()
    {
        $mockOperation = $this->createMock(CreateRecordOperation::class);

        $mockOperation
            ->expects(self::once())
            ->method('isDataFieldSet')
            ->with('pid')
            ->willReturn(false);

        $mockOperation
            ->expects(self::once())
            ->method('getStoragePid')
            ->willReturn(123);

        $mockOperation
            ->expects(self::once())
            ->method('setDataFieldForDataHandler')
            ->with('pid', 123);

        $event = new RecordOperationSetupEvent($mockOperation);

        (new SetPid())($event);
    }

    /**
     * @test
     */
    public function doesNotSetPidOnNonCreateOperation()
    {
        foreach ([UpdateRecordOperation::class, DeleteRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('isDataFieldSet')
                ->with('pid')
                ->willReturn(false);

            $mockOperation
                ->expects(self::never())
                ->method('getStoragePid');

            $mockOperation
                ->expects(self::never())
                ->method('setDataFieldForDataHandler');

            $event = new RecordOperationSetupEvent($mockOperation);

            (new SetPid())($event);
        }
    }

    /**
     * @test
     */
    public function doesNotSetPidIfAlreadySet()
    {
        $mockOperation = $this->createMock(CreateRecordOperation::class);

        $mockOperation
            ->expects(self::once())
            ->method('isDataFieldSet')
            ->with('pid')
            ->willReturn(true);

        $mockOperation
            ->expects(self::never())
            ->method('getStoragePid');

        $mockOperation
            ->expects(self::never())
            ->method('setDataFieldForDataHandler');

        $event = new RecordOperationSetupEvent($mockOperation);

        (new SetPid())($event);
    }
}
