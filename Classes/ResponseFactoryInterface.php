<?php

declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ResponseFactoryInterface
{
    /**
     * Returns a response with the given content and status code.
     *
     * @param array $data   Data to send
     * @param int          $status Status code of the response
     * @return ResponseInterface
     */
    public function createResponse(array $data, int $status): ResponseInterface;

    /**
     * Returns a response with the given message and status code.
     *
     * Some data (e.g. the format) will be read from the request.
     *
     * @param string|array         $data
     * @param int                  $status
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function createErrorResponse(array $data, int $status, InterestRequestInterface $request): ResponseInterface;

    /**
     * Returns a response with the given message and status code.
     *
     * Some data (e.g. the format) will be read from the request.
     *
     * @param array $data
     * @param int $status
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function createSuccessResponse(
        array $data,
        int $status,
        InterestRequestInterface $request
    ): ResponseInterface;
}
