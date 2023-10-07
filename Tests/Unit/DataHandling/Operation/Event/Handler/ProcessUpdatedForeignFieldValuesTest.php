<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\DataHandler;
use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\Message\RelationFieldValueMessage;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\ProcessUpdatedForeignFieldValues;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationInvocationEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ProcessUpdatedForeignFieldValuesTest extends UnitTestCase
{
    /**
     * @test
     */
    public function returnEarlyWhenNotUpdateOperation()
    {
        foreach ([DeleteRecordOperation::class, CreateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::never())
                ->method('retrieveMessage');

            $event = new RecordOperationInvocationEvent($mockOperation);

            (new ProcessUpdatedForeignFieldValues())($event);
        }
    }

    /**
     * @test
     */
    public function processWhenUpdateOperationAndReturnWhenNoMessages()
    {
            $mockOperation = $this->createMock(UpdateRecordOperation::class);

            $mockOperation
                ->expects(self::once())
                ->method('retrieveMessage')
                ->willReturn(null);

            $event = new RecordOperationInvocationEvent($mockOperation);

            (new ProcessUpdatedForeignFieldValues())($event);
    }

    /**
     * @test
     */
    public function correctlySetsCmdmap()
    {
        $messageValues = [
            ['tablename1', 'firstField', 123, [1, 2]],
            ['tablename2', 'firstField', 456, '4,6'],
            ['tablename3', 'secondField', 789, [7, 8, 9]],
        ];

        $relationReturns = [
            [
                'relationTableA' => [1, 2, 3],
                'relationTableB' => [4, 5, 6, 10]
            ],
            [
                'relationTableA' => [4, 5, 6],
            ],
            [
                'relationTableA' => [7, 8, 9],
                'relationTableB' => [4, 5, 6, 10]
            ],
        ];

        // Though technically correct, this array also illustrates that there is nothing preventing a record from being
        // deleted while it still has valid relations. There's currently no good/performant way to gather this
        // information, so in real life we have to assume that the RelationFieldValueMessage was generated sanely.
        $expectedCmdmap = [
            'relationTableA' => [
                1 => ['delete' => 1],
                2 => ['delete' => 1],
                3 => ['delete' => 1],
                5 => ['delete' => 1],
                7 => ['delete' => 1],
                8 => ['delete' => 1],
                9 => ['delete' => 1],
            ],
            'relationTableB' => [
                4 => ['delete' => 1],
                5 => ['delete' => 1],
                6 => ['delete' => 1],
                10 => ['delete' => 1],
            ],
        ];

        $mockDataHandler = $this->createMock(DataHandler::class);

        $mockDataHandler->cmdmap = [];

        $mockOperation = $this->createMock(UpdateRecordOperation::class);

        $messages = [];

        foreach ($messageValues as $messageValueSet) {
            $messages[] = new RelationFieldValueMessage(... $messageValueSet);
        }

        $mockOperation
            ->expects(self::exactly(count($messages) + 1))
            ->method('retrieveMessage')
            ->with(RelationFieldValueMessage::class)
            ->willReturnOnConsecutiveCalls(... [... $messages, null]);

        $mockOperation
            ->method('getDataHandler')
            ->willReturn($mockDataHandler);

        $partialMockEventHandler = $this->createPartialMock(
            ProcessUpdatedForeignFieldValues::class,
            ['getRelationsFromMessage']
        );

        $partialMockEventHandler
            ->expects(self::exactly(count($messageValues)))
            ->method('getRelationsFromMessage')
            ->withConsecutive(
                ... array_reduce(
                    $messages,
                    // Wrap each $message in an array.
                    fn (array $carry, RelationFieldValueMessage $message): array => [... $carry, [$message]],
                    []
                )
            )
            ->willReturnOnConsecutiveCalls(... $relationReturns);

        $event = new RecordOperationInvocationEvent($mockOperation);

        $partialMockEventHandler($event);

        self::assertEquals($expectedCmdmap, $mockDataHandler->cmdmap);
    }
}
