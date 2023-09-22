<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\ApplyFieldDataTransformations;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ApplyFieldDataTransformationsTest extends UnitTestCase
{
    /**
     * @test
     */
    public function returnsEarlyIfDeleteRecordOperation()
    {
        $mockOperation = $this->createMock(DeleteRecordOperation::class);

        $mockOperation
            ->expects(self::never())
            ->method('getSettings');

        $event = new RecordOperationSetupEvent($mockOperation);

        (new ApplyFieldDataTransformations())($event);
    }

    /**
     * @test
     */
    public function callsStdWrapWithCorrectArguments()
    {
        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $field1 = ['randomData' => StringUtility::getUniqueId()];

            $field2 = ['alsoRandomData' => StringUtility::getUniqueId()];

            $settingsArray = [
                'transformations.' => [
                    'tablename' => [],
                    'tablename.' => [
                        'field1.' => $field1,
                        'field2.' => $field2,
                    ],
                ],
            ];

            $dataArray = [
                'field1' => StringUtility::getUniqueId(),
                'field2' => StringUtility::getUniqueId(),
            ];

            $mockOperation
                ->expects(self::once())
                ->method('getSettings')
                ->willReturn($settingsArray);

            $mockOperation
                ->method('getTable')
                ->willReturn('tablename');

            $mockContentObjectRenderer = $this->createMock(ContentObjectRenderer::class);

            $mockContentObjectRenderer
                ->expects(self::exactly(2))
                ->method('stdWrap')
                ->withConsecutive(
                    [$dataArray['field1'], $field1],
                    [$dataArray['field2'], $field2]
                )
                ->willReturnOnConsecutiveCalls(
                    'field1return',
                    'field2return'
                );

            $mockOperation
                ->expects(self::exactly(2))
                ->method('setDataFieldForDataHandler')
                ->withConsecutive(
                    ['field1', 'field1return'],
                    ['field2', 'field2return'],
                );

            $mockOperation
                ->method('getContentObjectRenderer')
                ->willReturn($mockContentObjectRenderer);

            $mockOperation
                ->method('getDataForDataHandler')
                ->willReturn($dataArray);

            $event = new RecordOperationSetupEvent($mockOperation);

            (new ApplyFieldDataTransformations())($event);
        }
    }
}
