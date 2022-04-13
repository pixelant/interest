<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\RequestHandler;

use Pixelant\Interest\RequestHandler\AbstractRecordRequestHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AbstractRecordRequestHandlerTest extends UnitTestCase
{
    /**
     * @test
     * @dataProvider correctlyCompliesDataProvider
     */
    public function correctlyCompliesData(array $entryPoints, array $body, array $expectedConsecutive)
    {
        $body = json_encode($body);

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $body);
        rewind($stream);

        $request = new ServerRequest(
            'http://www.example.com/rest',
            'POST',
            $stream
        );

        $subjectMock = $this->getMockForAbstractClass(
            AbstractRecordRequestHandler::class,
            [
                $entryPoints,
                $request,
            ],
            '',
            true,
            true,
            true,
            ['handleSingleOperation']
        );

        $subjectMock
            ->expects(self::exactly(count($expectedConsecutive)))
            ->method('handleSingleOperation')
            ->withConsecutive(...$expectedConsecutive);

        $subjectMock->handle();
    }

    public function correctlyCompliesDataProvider()
    {
        return [
            'Single record' => [
                [
                    'table',
                    'remoteId',
                ],
                [
                    'data' => [
                        'title' => 'TEST',
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId',
                        '',
                        '',
                        [
                            'title' => 'TEST',
                        ],
                    ],
                ],
            ],
            'Single record with language in query' => [
                [
                    'table',
                    'remoteId',
                    'language',
                ],
                [
                    'data' => [
                        'title' => 'TEST',
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId',
                        'language',
                        '',
                        [
                            'title' => 'TEST',
                        ],
                    ],
                ],
            ],
            'Single record with language in data' => [
                [
                    'table',
                    'remoteId',
                ],
                [
                    'data' => [
                        'language' => [
                            'title' => 'TEST',
                        ],
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId',
                        'language',
                        '',
                        [
                            'title' => 'TEST',
                        ],
                    ],
                ],
            ],
            'Single record with remote ID in data' => [
                [
                    'table',
                ],
                [
                    'data' => [
                        'remoteId' => [
                            'title' => 'TEST',
                        ],
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId',
                        '',
                        '',
                        [
                            'title' => 'TEST',
                        ],
                    ],
                ],
            ],
            'Single record with remote ID and language in data' => [
                [
                    'table',
                ],
                [
                    'data' => [
                        'remoteId' => [
                            'language' => [
                                'title' => 'TEST',
                            ],
                        ],
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId',
                        'language',
                        '',
                        [
                            'title' => 'TEST',
                        ],
                    ],
                ],
            ],
            'Single record with table and remote ID in data' => [
                [],
                [
                    'data' => [
                        'table' => [
                            'remoteId' => [
                                'title' => 'TEST',
                            ],
                        ],
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId',
                        '',
                        '',
                        [
                            'title' => 'TEST',
                        ],
                    ],
                ],
            ],
            'Single record with table, remote ID and language in data' => [
                [],
                [
                    'data' => [
                        'table' => [
                            'remoteId' => [
                                'language' => [
                                    'title' => 'TEST',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId',
                        'language',
                        '',
                        [
                            'title' => 'TEST',
                        ],
                    ],
                ],
            ],
            'Multiple records with same table' => [
                [
                    'table',
                ],
                [
                    'data' => [
                        'remoteId1' => [
                            'title' => 'TEST1',
                        ],
                        'remoteId2' => [
                            'title' => 'TEST2',
                        ],
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId1',
                        '',
                        '',
                        [
                            'title' => 'TEST1',
                        ],
                    ],
                    [
                        'table',
                        'remoteId2',
                        '',
                        '',
                        [
                            'title' => 'TEST2',
                        ],
                    ],
                ],
            ],
            'Multiple records with same remoteId and multiple languages' => [
                [
                    'table',
                    'remoteId',
                ],
                [
                    'data' => [
                        'language1' => [
                            'title' => 'TEST1',
                        ],
                        'language2' => [
                            'title' => 'TEST2',
                        ],
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId',
                        'language1',
                        '',
                        [
                            'title' => 'TEST1',
                        ],
                    ],
                    [
                        'table',
                        'remoteId',
                        'language2',
                        '',
                        [
                            'title' => 'TEST2',
                        ],
                    ],
                ],
            ],
            'Multiple records with same table and remote ID and multiple languages' => [
                [
                    'table',
                ],
                [
                    'data' => [
                        'remoteId' => [
                            'language1' => [
                                'title' => 'TEST1',
                            ],
                            'language2' => [
                                'title' => 'TEST2',
                            ],
                        ],
                    ],
                ],
                [
                    [
                        'table',
                        'remoteId',
                        'language1',
                        '',
                        [
                            'title' => 'TEST1',
                        ],
                    ],
                    [
                        'table',
                        'remoteId',
                        'language2',
                        '',
                        [
                            'title' => 'TEST2',
                        ],
                    ],
                ],
                'Single record with multiple remote IDs and multiple languages in data' => [
                [
                    'table',
                ],
                [
                    'data' => [
                        'remoteId1' => [
                            'language1' => [
                                'title' => 'TEST1',
                            ],
                            'language2' => [
                                'title' => 'TEST2',
                            ],
                        ],
                        'remoteId2' => [
                            'language3' => [
                                'title' => 'TEST1',
                            ],
                            'language4' => [
                                'title' => 'TEST2',
                            ],
                        ],

                    ],
                ],
                [
                    [
                        'table',
                        'remoteId1',
                        'language1',
                        '',
                        [
                            'title' => 'TEST1',
                        ],
                    ],
                    [
                        'table',
                        'remoteId1',
                        'language2',
                        '',
                        [
                            'title' => 'TEST2',
                        ],
                    ],
                    [
                        'table',
                        'remoteId2',
                        'language3',
                        '',
                        [
                            'title' => 'TEST1',
                        ],
                    ],
                    [
                        'table',
                        'remoteId2',
                        'language4',
                        '',
                        [
                            'title' => 'TEST2',
                        ],
                    ],
                ],
                ],
                'Single record with table and remote ID in data' => [
                    [],
                    [
                        'data' => [
                            'table' => [
                                'remoteId' => [
                                    'title' => 'TEST',
                                ],
                            ],
                        ],
                    ],
                    [
                        [
                            'table',
                            'remoteId',
                            '',
                            '',
                            [
                                'title' => 'TEST',
                            ],
                        ],
                    ],
                ],
                'Single record with table, remote ID and language in data' => [
                    [],
                    [
                        'data' => [
                            'table1' => [
                                'remoteId1' => [
                                    'language1' => [
                                        'title' => 'TEST1',
                                    ],
                                ],
                            ],
                            'table2' => [
                                'remoteId2' => [
                                    'language2' => [
                                        'title' => 'TEST1',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        [
                            'table1',
                            'remoteId1',
                            'language1',
                            '',
                            [
                                'title' => 'TEST1',
                            ],
                        ],
                        [
                            'table2',
                            'remoteId2',
                            'language2',
                            '',
                            [
                                'title' => 'TEST2',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
