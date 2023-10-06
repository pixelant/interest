<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\PendingRelationMessage;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\PersistPendingRelationInformation;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationInvocationEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\PendingRelationsRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class PersistPendingRelationInformationTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    /**
     * @test
     */
    public function returnEarlyIfDeleteOperation()
    {
        $mockOperation = $this->createMock(DeleteRecordOperation::class);

        $mockOperation
            ->expects(self::never())
            ->method('getDataForDataHandler');

        $event = new RecordOperationInvocationEvent($mockOperation);

        (new PersistPendingRelationInformation())($event);
    }

    /**
     * @test
     */
    public function persistDataFromEachPendingRelationMessage()
    {
        $pendingRelationMessage1 = new PendingRelationMessage(
            'tablename1',
            'fieldname1',
            ['remoteId11', 'remoteId12', 'remoteId13']
        );

        $pendingRelationMessage2 = new PendingRelationMessage(
            'tablename2',
            'fieldname2',
            ['remoteId21', 'remoteId22', 'remoteId23']
        );

        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::exactly(3))
                ->method('retrieveMessage')
                ->with(PendingRelationMessage::class)
                ->willReturnOnConsecutiveCalls(
                    $pendingRelationMessage2,
                    $pendingRelationMessage1,
                    null
                );

            $mockOperation
                ->expects(self::exactly(2))
                ->method('getUid')
                ->willReturnOnConsecutiveCalls(
                    456,
                    123,
                );

            $repositoryMock = $this->createMock(PendingRelationsRepository::class);

            $repositoryMock
                ->expects(self::exactly(2))
                ->method('set')
                ->withConsecutive(
                    [
                        $pendingRelationMessage2->getTable(),
                        $pendingRelationMessage2->getField(),
                        456,
                        $pendingRelationMessage2->getRemoteIds(),
                    ],
                    [
                        $pendingRelationMessage1->getTable(),
                        $pendingRelationMessage1->getField(),
                        123,
                        $pendingRelationMessage1->getRemoteIds(),
                    ]
                );

            GeneralUtility::setSingletonInstance(
                PendingRelationsRepository::class,
                $repositoryMock
            );

            $event = new RecordOperationInvocationEvent($mockOperation);

            (new PersistPendingRelationInformation())($event);
        }
    }

    /**
     * @test
     */
    public function noPendingRelationMessagesMeansNoDatabaseSet()
    {
        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::exactly(1))
                ->method('retrieveMessage')
                ->with(PendingRelationMessage::class)
                ->willReturnOnConsecutiveCalls(
                    null
                );

            $repositoryMock = $this->createMock(PendingRelationsRepository::class);

            $repositoryMock
                ->expects(self::never())
                ->method('set');

            GeneralUtility::setSingletonInstance(
                PendingRelationsRepository::class,
                $repositoryMock
            );

            $event = new RecordOperationInvocationEvent($mockOperation);

            (new PersistPendingRelationInformation())($event);
        }
    }
}
