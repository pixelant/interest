<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\RemovePendingRelationsForCreatedRecord;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationInvocationEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RemovePendingRelationsForCreatedRecordTest extends UnitTestCase
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
            ->method('removeRemote');

        GeneralUtility::setSingletonInstance(PendingRelationsRepository::class, $mockRepository);

        $mockOperation = $this->createMock(CreateRecordOperation::class);

        $mockOperation
            ->expects(self::once())
            ->method('isSuccessful')
            ->willReturn(false);

        $event = new RecordOperationInvocationEvent($mockOperation);

        (new RemovePendingRelationsForCreatedRecord())($event);
    }

    /**
     * @test
     */
    public function doesNotProceedWhenUpdateOrDeleteOperation()
    {
        $mockRepository = $this->createMock(PendingRelationsRepository::class);

        $mockRepository
            ->expects(self::never())
            ->method('removeRemote');

        GeneralUtility::setSingletonInstance(PendingRelationsRepository::class, $mockRepository);

        foreach ([UpdateRecordOperation::class, DeleteRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('isSuccessful')
                ->willReturn(true);

            $event = new RecordOperationInvocationEvent($mockOperation);

            (new RemovePendingRelationsForCreatedRecord())($event);
        }
    }

    /**
     * @test
     */
    public function removeRemoteIsCalledWithRemoteId()
    {
        $mockRepository = $this->createMock(PendingRelationsRepository::class);

        $mockRepository
            ->expects(self::exactly(1))
            ->method('removeRemote')
            ->with('remoteId');

        GeneralUtility::setSingletonInstance(PendingRelationsRepository::class, $mockRepository);

        $mockOperation = $this->createMock(CreateRecordOperation::class);

        $mockOperation
            ->expects(self::once())
            ->method('isSuccessful')
            ->willReturn(true);

        $mockOperation
            ->expects(self::once())
            ->method('getRemoteId')
            ->willReturn('remoteId');

        $event = new RecordOperationInvocationEvent($mockOperation);

        (new RemovePendingRelationsForCreatedRecord())($event);
    }
}
