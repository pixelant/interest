<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationInvocationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\FixSortingPositionsOnRemoteRelationRecords;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class FixSortingPositionsOnRemoteRelationRecordsTest extends UnitTestCase
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

        $recordOperationMock = $this
            ->getMockBuilder(UpdateRecordOperation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setDataForDataHandler', 'getDataForDataHandler', 'isSuccessful'])
            ->getMock();

        $recordOperationMock
            ->method('getDataForDataHandler')
            ->willReturn($localRecordData);

        $recordOperationMock
            ->method('isSuccessful')
            ->willReturn(true);

        $event = new RecordOperationInvocationEvent($recordOperationMock);

        $subjectMock = $this
            ->getMockBuilder(FixSortingPositionsOnRemoteRelationRecords::class)
            ->onlyMethods(['getMmFieldConfigurations', 'orderOnForeignSideOfRelation', 'persistData'])
            ->getMock();

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
