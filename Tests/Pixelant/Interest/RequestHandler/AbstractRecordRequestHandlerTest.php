<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Pixelant\Interest\RequestHandler;

use Pixelant\Interest\RequestHandler\AbstractRecordRequestHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers AbstractRecordRequestHandler
 */
final class AbstractRecordRequestHandlerTest extends UnitTestCase
{
    /**
     * @test
     */
    public function correctlyCompilesData(): void
    {
        $controllerArguments = [
            [
                'tableName',
                'remoteId',
                'language',
                'workspace',
            ],
            new ServerRequest(),
        ];

        $mock = $this->getMockForAbstractClass(
            AbstractRecordRequestHandler::class,
            $controllerArguments
        );

        $mock
            ->expects($this->exactly(1))
            ->method('handleSingleOperation')
            ->with(
                'tableName',
                'remoteId',
                'language',
                'workspace'
            );
    }
}
