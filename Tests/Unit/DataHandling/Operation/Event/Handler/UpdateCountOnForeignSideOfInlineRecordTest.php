<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\UpdateCountOnForeignSideOfInlineRecord;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationInvocationEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class UpdateCountOnForeignSideOfInlineRecordTest extends UnitTestCase
{
    /**
     * @test
     */
    public function willNotExecuteOnCreateAndUpdateOperation()
    {
        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $partialSubjectMock = $this->createPartialMock(
                UpdateCountOnForeignSideOfInlineRecord::class,
                ['getRecordInlineFieldRelationCount']
            );

            $partialSubjectMock
                ->expects(self::never())
                ->method('getRecordInlineFieldRelationCount');

            $event = new RecordOperationInvocationEvent($mockOperation);

            $partialSubjectMock($event);
        }
    }

    /**
     * @test
     */
    public function willExecuteOnDeleteOperation()
    {
        $mockOperation = $this->createMock(DeleteRecordOperation::class);

        $partialSubjectMock = $this->createPartialMock(
            UpdateCountOnForeignSideOfInlineRecord::class,
            ['getRecordInlineFieldRelationCount']
        );

        $event = new RecordOperationInvocationEvent($mockOperation);

        $partialSubjectMock
            ->expects(self::once())
            ->method('getRecordInlineFieldRelationCount')
            ->with($event);

        $partialSubjectMock($event);
    }
}
