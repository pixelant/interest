<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\RemoveFieldsWithNullValue;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\SanitizeFieldValues;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class SanitizeFieldValuesTest extends UnitTestCase
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

        (new SanitizeFieldValues())($event);
    }

    /**
     * @test
     */
    public function csvRelationalFieldsAreExploded()
    {
        $dataForDataHandler = [
            'arrayRelationField' => ['relation1RemoteId', 'relation2RemoteId', 'relation3RemoteId'],
            'csvRelationField' => 'relation4RemoteId,relation5RemoteId,relation6RemoteId',
        ];

        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('getDataForDataHandler')
                ->willReturn($dataForDataHandler);

            $mockOperation
                ->expects(self::once())
                ->method('setDataFieldForDataHandler')
                ->with('csvRelationField', ['relation4RemoteId', 'relation5RemoteId', 'relation6RemoteId']);

            $partialMockEventHandler = $this->createPartialMock(
                SanitizeFieldValues::class,
                ['isRelationalField']
            );

            $partialMockEventHandler
                ->method('isRelationalField')
                ->willReturn(true);

            $event = new RecordOperationSetupEvent($mockOperation);

            $partialMockEventHandler($event);
        }
    }

    /**
     * @test
     */
    public function floatIntegerAndStringAreNotModified()
    {
        $dataForDataHandler = [
            'floatField' => 1.234,
            'integerField' => 56,
            'stringField' => 'aStringValue',
        ];

        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('getDataForDataHandler')
                ->willReturn($dataForDataHandler);

            $mockOperation
                ->expects(self::never())
                ->method('setDataFieldForDataHandler');

            $partialMockEventHandler = $this->createPartialMock(
                SanitizeFieldValues::class,
                ['isRelationalField']
            );

            $partialMockEventHandler
                ->method('isRelationalField')
                ->willReturn(false);

            $event = new RecordOperationSetupEvent($mockOperation);

            $partialMockEventHandler($event);
        }
    }

    /**
     * @test
     * @dataProvider unsupportedValueTypeDataProvider
     */
    public function unsupportedValueTypeThrowsException(array $dataForDataHandler)
    {
        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('getDataForDataHandler')
                ->willReturn($dataForDataHandler);

            $mockOperation
                ->expects(self::never())
                ->method('setDataFieldForDataHandler');

            $partialMockEventHandler = $this->createPartialMock(
                SanitizeFieldValues::class,
                ['isRelationalField']
            );

            $partialMockEventHandler
                ->method('isRelationalField')
                ->willReturn(false);

            $event = new RecordOperationSetupEvent($mockOperation);

            self::expectException(InvalidArgumentException::class);

            $partialMockEventHandler($event);
        }
    }

    public function unsupportedValueTypeDataProvider(): array
    {
        return [
            [['objectField' => new \stdClass()]],
            [['resourceField' => fopen('php://memory', 'r')]],
            [['nullField' => null]],
        ];
    }
}
