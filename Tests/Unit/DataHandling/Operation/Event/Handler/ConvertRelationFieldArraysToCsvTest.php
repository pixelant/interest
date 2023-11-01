<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\ApplyFieldDataTransformations;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\ConvertRelationFieldArraysToCsv;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ConvertRelationFieldArraysToCsvTest extends UnitTestCase
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

            $dataArray = [
                'field1' => StringUtility::getUniqueId(),
                'field2' => [StringUtility::getUniqueId(), StringUtility::getUniqueId(), StringUtility::getUniqueId()],
            ];

            $mockOperation
                ->method('getDataForDataHandler')
                ->willReturn($dataArray);

            $mockOperation
                ->expects(self::exactly(1))
                ->method('setDataFieldForDataHandler')
                ->with(
                    'field2',
                    implode(',', $dataArray['field2'])
                );

            $event = new RecordOperationSetupEvent($mockOperation);

            (new ConvertRelationFieldArraysToCsv())($event);
        }
    }
}
