<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\RemoveFieldsWithNullValue;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RemoveFieldsWithNullValueTest extends UnitTestCase
{
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

        (new RemoveFieldsWithNullValue())($event);
    }

    /**
     * @test
     */
    public function correctlyRemovesEmptyValuesFromRelationArrays()
    {
        $dataForDataHandler = [
            'nonRelationField' => 'nonRelationFieldValue',
            'emptyRelationField' => [],
            'relationFieldWithNoEmptyValues' => ['remoteId1', 'remoteId2', 'remoteId3'],
            'relationFieldWithSomeNullValues' => ['remoteId4', null, null, 'remoteId5', 'remoteId6'],
            'relationFieldWithOnlyNullValues' => [null, null, null],
            'nullValueField1' => null,
            'nullValueField2' => null,
        ];

        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('getDataForDataHandler')
                ->willReturn($dataForDataHandler);

            $mockOperation
                ->expects(self::exactly(2))
                ->method('unsetDataField')
                ->withConsecutive(['nullValueField1'], ['nullValueField2']);

            $event = new RecordOperationSetupEvent($mockOperation);

            (new RemoveFieldsWithNullValue())($event);
        }
    }
}
