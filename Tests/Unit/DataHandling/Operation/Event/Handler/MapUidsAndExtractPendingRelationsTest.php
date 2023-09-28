<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\MapUidsAndExtractPendingRelations;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\PendingRelationMessage;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class MapUidsAndExtractPendingRelationsTest extends UnitTestCase
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

        $event = new RecordOperationSetupEvent($mockOperation);

        (new MapUidsAndExtractPendingRelations())($event);
    }

    /**
     * @test
     */
    public function setsExistingUidsAndIssuesPendingRelationMessagesForOthers()
    {
        $testData = [
            'textField' => 'textFieldContent',
            'numberField' => 9876,
            'relationField1' => [
                'relation1',
                'relation2',
                'relation3',
            ],
            'relationField2' => [
                'relation3',
                'relation4',
                'relation5',
            ],
        ];

        $remoteIdExistence = [
            'relation1' => false,
            'relation2' => true,
            'relation3' => true,
            'relation4' => false,
            'relation5' => false,
        ];

        $remoteIdToUid = [
            'relation1' => 2,
            'relation2' => 4,
            'relation3' => 6,
            'relation4' => 8,
            'relation5' => 10,
        ];

        $table = 'tablename';

        $GLOBALS['TCA'][$table]['columns'] = [
            'relationField1' => [
                'config' => [
                    'type' => 'notGroup'
                ],
            ],
            'relationField2' => [
                'config' => [
                    'type' => 'group',
                    'allowed' => '*',
                ],
            ],
        ];

        $mappingRepository = $this->createMock(RemoteIdMappingRepository::class);

        $mappingRepository
            ->method('table')
            ->willReturn('tablename');

        $mappingRepository
            ->method('exists')
            ->willReturnCallback(function ($remoteId) use ($remoteIdExistence) {
                return $remoteIdExistence[$remoteId];
            });

        $mappingRepository
            ->method('get')
            ->willReturnCallback(function ($remoteId) use ($remoteIdToUid) {
                return $remoteIdToUid[$remoteId];
            });

        GeneralUtility::setSingletonInstance(RemoteIdMappingRepository::class, $mappingRepository);

        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->method('getTable')
                ->willReturn($table);

            $mockOperation
                ->expects(self::once())
                ->method('getDataForDataHandler')
                ->willReturn($testData);

            $mockOperation
                ->expects(self::exactly(2))
                ->method('setDataFieldForDataHandler')
                ->withConsecutive(
                    ['relationField1', [4, 6]],
                    ['relationField2', ['tablename_6']]
                );

            $dispatchCount = self::exactly(2);

            $mockOperation
                ->expects($dispatchCount)
                ->method('dispatchMessage')
                ->with(self::callback(function (PendingRelationMessage $message) use ($dispatchCount, $table) {
                    $expected = [
                        ['relationField1', ['relation1']],
                        ['relationField2', ['relation4', 'relation5']],
                    ];

                    self::assertEquals(
                        $table,
                        $message->getTable(),
                        'Tablename invocation #' . $dispatchCount->getInvocationCount()
                    );

                    self::assertEquals(
                        $expected[$dispatchCount->getInvocationCount() - 1][0],
                        $message->getField(),
                        'Field name invocation #' . $dispatchCount->getInvocationCount()
                    );

                    self::assertEquals(
                        $expected[$dispatchCount->getInvocationCount() - 1][1],
                        $message->getRemoteIds(),
                        'Remote IDs invocation #' . $dispatchCount->getInvocationCount()
                    );

                    return true;
                }));

            $event = new RecordOperationSetupEvent($mockOperation);

            (new MapUidsAndExtractPendingRelations())($event);
        }
    }
}
