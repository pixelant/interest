<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\DataHandlerSuccessMessage;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\ProcessDatamap;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationInvocationEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ProcessDatamapTest extends UnitTestCase
{
    /**
     * @test
     */
    public function returnEarlyWhenEmptyDatamap()
    {
        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockDataHandler = $this->createMock(DataHandler::class);

            $mockDataHandler
                ->expects(self::never())
                ->method('process_datamap');

            $mockDataHandler->datamap = [];

            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('getDataHandler')
                ->willReturn($mockDataHandler);

            $event = new RecordOperationInvocationEvent($mockOperation);

            (new ProcessDatamap())($event);
        }
    }

    /**
     * @test
     */
    public function willProcessDatamapAndSetStatus()
    {
        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            foreach ([['iAmAnError'], []] as $errorLogKey => $errorLog) {
                $mockDataHandler = $this->createMock(DataHandler::class);

                $mockDataHandler
                    ->expects(self::once())
                    ->method('process_datamap');

                $mockDataHandler->datamap = ['iAmNotEmpty'];

                $mockDataHandler->errorLog = $errorLog;

                $mockOperation = $this->createMock($operationClass);

                $mockOperation
                    ->method('getDataHandler')
                    ->willReturn($mockDataHandler);

                $mockOperation
                    ->expects(self::once())
                    ->method('dispatchMessage')
                    ->with(
                        self::callback(
                            function (DataHandlerSuccessMessage $message) use ($errorLogKey) {
                                self::assertEquals($message->isSuccess(), (bool)$errorLogKey);

                                return true;
                            }
                        )
                    );

                $event = new RecordOperationInvocationEvent($mockOperation);

                (new ProcessDatamap())($event);
            }
        }
    }
}
