<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation;
use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\RelationSortingAsMetaData;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationSetupEvent;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RelationSortingAsMetaDataTest extends UnitTestCase
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
            ->method('getRemoteId');

        $partialMockEventHandler = $this->createPartialMock(
            RelationSortingAsMetaData::class,
            ['getSortedMmRelationFieldConfigurations']
        );

        $partialMockEventHandler
            ->expects(self::never())
            ->method('getSortedMmRelationFieldConfigurations');

        $event = new RecordOperationSetupEvent($mockOperation);

        $partialMockEventHandler($event);
    }

    /**
     * @test
     */
    public function returnEarlyIfNoMmFieldConfigurations()
    {
        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::never())
                ->method('getRemoteId');

            $partialMockEventHandler = $this->createPartialMock(
                RelationSortingAsMetaData::class,
                ['getSortedMmRelationFieldConfigurations', 'addSortingIntentToMetaData']
            );

            $partialMockEventHandler
                ->expects(self::once())
                ->method('getSortedMmRelationFieldConfigurations')
                ->willReturn([]);

            $partialMockEventHandler
                ->expects(self::never())
                ->method('addSortingIntentToMetaData');

            $event = new RecordOperationSetupEvent($mockOperation);

            $partialMockEventHandler($event);
        }
    }

    /**
     * @test
     */
    public function getSortedMmRelationFieldConfigurationsReturnsEmptyWhenNoColumns()
    {
        $tableName = 'tablename';

        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->expects(self::once())
                ->method('getTable')
                ->willReturn($tableName);

            $event = new RecordOperationSetupEvent($mockOperation);

            $partialMockEventHandler = $this->createPartialMock(
                RelationSortingAsMetaData::class,
                ['getEvent']
            );

            $partialMockEventHandler
                ->method('getEvent')
                ->willReturn($event);

            $fieldConfigurations = $partialMockEventHandler->getSortedMmRelationFieldConfigurations();

            self::assertEquals([], $fieldConfigurations);
        }
    }

    /**
     * @test
     */
    public function getSortedMmRelationFieldConfigurationsReturnsCorrectData()
    {
        $tableName = 'tablename';

        $this->setTcaConfiguration($tableName);

        $expectedFieldConfiguration = [
            'fieldWithNoMaxItems' => [
                'MM' => 'mmTableName',
            ],
            'fieldWithMaxItemsTwo' => [
                'MM' => 'mmTableName',
                'maxitems' => 2,
            ],
        ];

        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->method('getTable')
                ->willReturn($tableName);

            $mockOperation
                ->method('getRemoteId')
                ->willReturn('remoteId');

            $event = new RecordOperationSetupEvent($mockOperation);

            $partialMockEventHandler = $this->createPartialMock(
                RelationSortingAsMetaData::class,
                ['getEvent', 'getTcaFieldConfigurationAndRespectColumnsOverrides']
            );

            $partialMockEventHandler
                ->method('getEvent')
                ->willReturn($event);

            $partialMockEventHandler
                ->method('getTcaFieldConfigurationAndRespectColumnsOverrides')
                ->willReturnCallback(
                    fn (AbstractRecordOperation $recordOperation, string $field)
                        => $GLOBALS['TCA'][$recordOperation->getTable()]['columns'][$field]['config']
                );

            $fieldConfigurations = $partialMockEventHandler->getSortedMmRelationFieldConfigurations();

            self::assertEquals($expectedFieldConfiguration, $fieldConfigurations);
        }
    }

    /**
     * @test
     */
    public function addSortingIntentToMetaDataSetsCorrectMetaData()
    {
        $tableName = 'tablename';

        $this->setTcaConfiguration($tableName);

        $dataForDataHandler = [
            'fieldWithNoRelation' => 'fieldWithNoRelationValue',
            'fieldWithNoMaxItems' => [
                'fieldWithNoMaxItemsRelation1',
                'fieldWithNoMaxItemsRelation2' ,
                'fieldWithNoMaxItemsRelation3',
            ],
            'fieldWithMaxItemsOne' => 'fieldWithMaxItemsOneValue',
            'fieldWithMaxItemsTwo' => 'fieldWithMaxItemsTwoRelation1,fieldWithMaxItemsTwoRelation2',
        ];

        $expectedSortingIntents = [
            'fieldWithNoMaxItems' => [
                'fieldWithNoMaxItemsRelation1',
                'fieldWithNoMaxItemsRelation2' ,
                'fieldWithNoMaxItemsRelation3',
            ],
            'fieldWithMaxItemsTwo' => [
                'fieldWithMaxItemsTwoRelation1',
                'fieldWithMaxItemsTwoRelation2',
            ],
        ];

        foreach ([CreateRecordOperation::class, UpdateRecordOperation::class] as $operationClass) {
            $mockRepository = $this->createMock(RemoteIdMappingRepository::class);

            $mockRepository
                ->expects(self::once())
                ->method('setMetaDataValue')
                ->with(
                    'remoteId',
                    RelationSortingAsMetaData::class,
                    $expectedSortingIntents
                );

            GeneralUtility::setSingletonInstance(RemoteIdMappingRepository::class, $mockRepository);

            $mockOperation = $this->createMock($operationClass);

            $mockOperation
                ->method('getTable')
                ->willReturn($tableName);

            $mockOperation
                ->method('getRemoteId')
                ->willReturn('remoteId');

            $mockOperation
                ->method('getDataForDataHandler')
                ->willReturn($dataForDataHandler);

            $event = new RecordOperationSetupEvent($mockOperation);

            (new RelationSortingAsMetaData())($event);
        }
    }

    /**
     * @param string $tableName
     */
    protected function setTcaConfiguration(string $tableName): void
    {
        $GLOBALS['TCA'][$tableName]['columns'] = [
            'fieldWithNoRelation' => [
                'config' => [],
            ],
            'fieldWithNoMaxItems' => [
                'config' => [
                    'MM' => 'mmTableName',
                ],
            ],
            'fieldWithMaxItemsOne' => [
                'config' => [
                    'MM' => 'mmTableName',
                    'maxitems' => 1,
                ],
            ],
            'fieldWithMaxItemsTwo' => [
                'config' => [
                    'MM' => 'mmTableName',
                    'maxitems' => 2,
                ],
            ],
        ];
    }
}
