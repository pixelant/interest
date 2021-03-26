<?php

declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;

class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * @param array $data
     * @param int $status
     * @return ResponseInterface
     */
    public function createResponse(array $data, int $status): ResponseInterface
    {
        $responseClass = $this->getResponseImplementationClass();
        /** @var JsonResponse $response */
        $response = new $responseClass();
        $response = $response->withStatus($status);
        $response->setPayload($data);

        return $response;
    }

    public function createErrorResponse(array $data, int $status, InterestRequestInterface $request): ResponseInterface
    {
        return $this->createFormattedResponse($data, $status, true, $request);
    }

    public function createSuccessResponse(
        array $data,
        int $status,
        InterestRequestInterface $request
    ): ResponseInterface {
        return $this->createFormattedResponse($data, $status, false, $request);
    }

    /**
     * @return string
     */
    private function getResponseImplementationClass()
    {
        return JsonResponse::class;
    }

    /**
     * Returns a response with the given message and status code.
     *
     * @param array $data Data to send
     * @param int $status Status code of the response
     * @param bool $forceError If TRUE the response will be treated as an error,
     *                         otherwise any status below 400 will be a normal response
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    private function createFormattedResponse(
        array $data,
        int $status,
        bool $forceError,
        InterestRequestInterface $request
    ): ResponseInterface {
        $responseClass = $this->getResponseImplementationClass();
        /** @var JsonResponse $response */
        $response = new $responseClass();
        $response = $response->withStatus($status);
        $response = $response->withAddedHeader('Access-Control-Allow-Origin', '*');
        $response->setPayload($data);

        return $response;
    }
}
