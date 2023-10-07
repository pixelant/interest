<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\RemovePendingRelationsForDeletedRecord;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationInvocationEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RemovePendingRelationsForDeletedRecordTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @test
     */
    public function doesNotProceedIfOperationWasUnsuccessful()
    {
        $mockRepository = $this->createMock(PendingRelationsRepository::class);

        $mockRepository
            ->expects(self::never())
            ->method('removeLocal');

        GeneralUtility::setSingletonInstance(PendingRelationsRepository::class, $mockRepository);

        $mockOperation = $this->createMock(DeleteRecordOperation::class);

        $mockOperation
            ->expects(self::once())
            ->method('isSuccessful')
            ->willReturn(false);

        $event = new RecordOperationInvocationEvent($mockOperation);

        (new RemovePendingRelationsForDeletedRecord())($event);
    }

    /**
     * @test
     */
    public function doesNotProceedWhenUpdateOrDeleteOperation()
    {
        $mockRepository = $this->createMock(PendingRelationsRepository::class);

        $mockRepository
            ->expects(self::never())
            ->method('removeLocal');

        GeneralUtility::setSingletonInstance(PendingRelationsRepository::class, $mockRepository);

        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('isSuccessful')
                ->willReturn(true);

            $event = new RecordOperationInvocationEvent($mockOperation);

            (new RemovePendingRelationsForDeletedRecord())($event);
        }
    }

    /**
     * @test
     */
    public function removeRemoteIsCalledWithTableAndUidAndNoField()
    {
        $mockRepository = $this->createMock(PendingRelationsRepository::class);

        $mockRepository
            ->expects(self::exactly(1))
            ->method('removeLocal')
            ->with('tablename', null, 123);

        GeneralUtility::setSingletonInstance(PendingRelationsRepository::class, $mockRepository);

        $mockOperation = $this->createMock(DeleteRecordOperation::class);

        $mockOperation
            ->expects(self::once())
            ->method('isSuccessful')
            ->willReturn(true);

        $mockOperation
            ->expects(self::once())
            ->method('getTable')
            ->willReturn('tablename');

        $mockOperation
            ->expects(self::once())
            ->method('getUid')
            ->willReturn(123);

        $event = new RecordOperationInvocationEvent($mockOperation);

        (new RemovePendingRelationsForDeletedRecord())($event);
    }
}
