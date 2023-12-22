<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\CopyRecordOperation;
use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\AssignUidOfCopyResultToRemoteId;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationInvocationEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AssignUidOfCopyResultToRemoteIdTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @test
     */
    public function assignsUidToRemoteIdOnSuccessfulCopyOperation()
    {
        $resultingRemoteId = StringUtility::getUniqueId();

        $originalUid = 122;

        $copyUid = 123;

        $mockDataHandler = $this->createMock(DataHandler::class);

        $mockDataHandler->copyMappingArray_merged = [
            'tableName' => [
                $originalUid => $copyUid,
            ],
        ];

        $mockOperation = $this->createMock(CopyRecordOperation::class);

        $mockOperation
            ->expects(self::once())
            ->method('isSuccessful')
            ->willReturn(true);

        $mockOperation
            ->expects(self::once())
            ->method('getUid')
            ->willReturn($originalUid);

        $mockOperation
            ->expects(self::once())
            ->method('getDataHandler')
            ->willReturn($mockDataHandler);

        $mockOperation
            ->expects(self::once())
            ->method('getTable')
            ->willReturn('tableName');

        $mockOperation
            ->expects(self::once())
            ->method('getResultingRemoteId')
            ->willReturn($resultingRemoteId);

        $mockMappingRepository = $this->createMock(RemoteIdMappingRepository::class);

        $mockMappingRepository
            ->expects(self::once())
            ->method('add')
            ->with(
                $resultingRemoteId,
                'tableName',
                $copyUid,
                $mockOperation
            );

        GeneralUtility::setSingletonInstance(RemoteIdMappingRepository::class, $mockMappingRepository);

        $event = new RecordOperationInvocationEvent($mockOperation);

        (new AssignUidOfCopyResultToRemoteId())($event);
    }

    /**
     * @test
     */
    public function doesNotAssignUidToRemoteIdOnUnsuccessfulCopyOperation()
    {
        $mockOperation = $this->createMock(CopyRecordOperation::class);

        $mockOperation
            ->expects(self::once())
            ->method('isSuccessful')
            ->willReturn(false);

        $mockOperation
            ->expects(self::never())
            ->method('getDataHandler');

        $event = new RecordOperationInvocationEvent($mockOperation);

        (new AssignUidOfCopyResultToRemoteId())($event);
    }

    /**
     * @test
     */
    public function doesNotAssignUidToRemoteIdIfOperationIsNotCopy()
    {
        foreach (
            [
                CreateRecordOperation::class,
                DeleteRecordOperation::class,
                UpdateRecordOperation::class,
            ] as $operationClass
        ) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('isSuccessful')
                ->willReturn(true);

            $mockOperation
                ->expects(self::never())
                ->method('getDataHandler');

            $event = new RecordOperationInvocationEvent($mockOperation);

            (new AssignUidOfCopyResultToRemoteId())($event);
        }
    }
}
