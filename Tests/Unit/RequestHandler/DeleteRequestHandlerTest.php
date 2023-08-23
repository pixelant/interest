<?php

declare(strict_types=1);

namespace Pixelant\Interest\Tests\Unit\RequestHandler;

use Pixelant\Interest\RequestHandler\DeleteRequestHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DeleteRequestHandlerTest extends UnitTestCase
{
    /**
     * @test
     */
    public function emptyRequestBodyIsNoProblem()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, '');
        rewind($stream);

        $request = new ServerRequest(
            'http://www.example.com/rest',
            'DELETE',
            $stream
        );

        $deleteHandlerMock = $this->getMockBuilder(DeleteRequestHandler::class)
            ->setConstructorArgs([
                [
                    'table',
                    'remoteId',
                ],
                $request,
            ])
            ->setMethodsExcept(['handle'])
            ->getMock();

        $response = $deleteHandlerMock->handle();

        self::assertEquals(200, $response->getStatusCode());
    }
}
