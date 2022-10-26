<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\ForeignRelationSortingEventHandler;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use Pixelant\Interest\Utility\CompatibilityUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ForeignRelationSortingEventHandlerTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSingletonInstances = true;
    }

    /**
     * @test
     * @dataProvider invokingGeneratesCorrectSortingDataDataProvider
     */
    public function invokingGeneratesCorrectSortingData(
        array $localRecordData,
        array $mmFieldConfiguration,
        array $foreignSideOrderReturns,
        array $persistDataArgument
    ) {
        $mappingRepositoryMock = $this->createMock(RemoteIdMappingRepository::class);

        GeneralUtility::setSingletonInstance(RemoteIdMappingRepository::class, $mappingRepositoryMock);

        if (CompatibilityUtility::typo3VersionIsLessThan('10')) {
            $recordOperationMock = $this
                ->getMockBuilder(UpdateRecordOperation::class)
                ->disableOriginalConstructor()
                ->setMethods(['setDataForDataHandler', 'getDataForDataHandler'])
                ->getMock();
        } else {
            $recordOperationMock = $this
                ->getMockBuilder(UpdateRecordOperation::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['setDataForDataHandler', 'getDataForDataHandler'])
                ->getMock();
        }

        $recordOperationMock
            ->method('getDataForDataHandler')
            ->willReturn($localRecordData);

        $event = new AfterRecordOperationEvent($recordOperationMock);

        if (CompatibilityUtility::typo3VersionIsLessThan('10')) {
            $subjectMock = $this
                ->getMockBuilder(ForeignRelationSortingEventHandler::class)
                ->setMethods(['getMmFieldConfigurations', 'orderOnForeignSideOfRelation', 'persistData'])
                ->getMock();
        } else {
            $subjectMock = $this
                ->getMockBuilder(ForeignRelationSortingEventHandler::class)
                ->onlyMethods(['getMmFieldConfigurations', 'orderOnForeignSideOfRelation', 'persistData'])
                ->getMock();
        }

        $subjectMock
            ->method('getMmFieldConfigurations')
            ->willReturn($mmFieldConfiguration);

        $subjectMock
            ->method('orderOnForeignSideOfRelation')
            ->willReturnOnConsecutiveCalls(... $foreignSideOrderReturns);

        $subjectMock
            ->expects(self::once())
            ->method('persistData')
            ->with($persistDataArgument);

        $subjectMock->__invoke($event);
    }

    /**
     * @return array
     */
    public function invokingGeneratesCorrectSortingDataDataProvider(): array
    {
        return [
            'Single group' => [
                [
                    'fieldName' => [99],
                ],
                [
                    'fieldName' => [
                        'type' => 'group',
                        'allowed' => 'foreignTableName',
                    ],
                ],
                [
                    [
                        'foreignTableName' => [
                            '99' => [
                                'fieldName' => [1, 2, 3, 4, 5],
                            ],
                        ],
                    ],
                ],
                [
                    'foreignTableName' => [
                        '99' => [
                            'fieldName' => [1, 2, 3, 4, 5],
                        ],
                    ],
                ],
            ],
            'Double group' => [
                [
                    'fieldName' => [98, 99],
                ],
                [
                    'fieldName' => [
                        'type' => 'group',
                        'allowed' => 'foreignTableName',
                    ],
                ],
                [
                    [
                        'foreignTableName' => [
                            '98' => [
                                'fieldName' => [1, 2, 3, 4, 5],
                            ],
                        ],
                    ],
                    [
                        'foreignTableName' => [
                            '99' => [
                                'fieldName' => [6, 7, 8],
                            ],
                        ],
                    ],
                ],
                [
                    'foreignTableName' => [
                        '98' => [
                            'fieldName' => [1, 2, 3, 4, 5],
                        ],
                        '99' => [
                            'fieldName' => [6, 7, 8],
                        ],
                    ],
                ],
            ],
            'Single inline' => [
                [
                    'fieldName' => [99],
                ],
                [
                    'fieldName' => [
                        'type' => 'inline',
                        'foreign_table' => 'foreignTableName',
                    ],
                ],
                [
                    [
                        'foreignTableName' => [
                            '99' => [
                                'fieldName' => [1, 2, 3, 4, 5],
                            ],
                        ],
                    ],
                ],
                [
                    'foreignTableName' => [
                        '99' => [
                            'fieldName' => [1, 2, 3, 4, 5],
                        ],
                    ],
                ],
            ],
            'Double inline' => [
                [
                    'fieldName' => [98, 99],
                ],
                [
                    'fieldName' => [
                        'type' => 'inline',
                        'foreign_table' => 'foreignTableName',
                    ],
                ],
                [
                    [
                        'foreignTableName' => [
                            '98' => [
                                'fieldName' => [1, 2, 3, 4, 5],
                            ],
                        ],
                    ],
                    [
                        'foreignTableName' => [
                            '99' => [
                                'fieldName' => [6, 7, 8],
                            ],
                        ],
                    ],
                ],
                [
                    'foreignTableName' => [
                        '98' => [
                            'fieldName' => [1, 2, 3, 4, 5],
                        ],
                        '99' => [
                            'fieldName' => [6, 7, 8],
                        ],
                    ],
                ],
            ],
        ];
    }
}
