<?php
declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Http\ServerRequestProxyTrait;
use Pixelant\Interest\Domain\Model\ResourceType;
use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Pixelant\Interest\Domain\Model\Format;

class Request implements ServerRequestInterface, InterestRequestInterface
{
    use ServerRequestProxyTrait;

    /**
     * @var ServerRequestInterface
     */
    private ServerRequestInterface $originalRequest;

    /**
     * @var string
     */
    private string $originalPath;

    /**
     * @var ResourceType
     */
    private ResourceType $resourceType;

    /**
     * @var UriInterface
     */
    private UriInterface $internalUri;

    /**
     * @var Format
     */
    private Format $format;

    /**
     * Constructor for a new request with the given Server Request, resource type and format
     *
     * @param ServerRequestInterface $originalRequest
     * @param UriInterface $internalUri
     * @param string $originalPath
     * @param ResourceType $resourceType
     * @param Format $format
     */
    public function __construct(
        ServerRequestInterface $originalRequest,
        UriInterface $internalUri,
        string $originalPath,
        ResourceType $resourceType,
        Format $format
    ) {

        $this->originalRequest = $originalRequest;
        $this->originalPath = $originalPath;
        $this->resourceType = $resourceType;
        $this->internalUri = $internalUri;
        $this->format = $format;
    }
    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->internalUri->getPath();
    }

    /**
     * @return ServerRequestInterface
     */
    public function getOriginalRequest(): ServerRequestInterface
    {
        return $this->originalRequest;
    }

    /**
     * @param ServerRequestInterface $request
     * @return $this
     */
    protected function setOriginalRequest(ServerRequestInterface $request): Request
    {
        $this->originalRequest = $request;

        return $this;
    }

    public function getResourceType(): ResourceType
    {
        return $this->resourceType;
    }

    public function getSendData()
    {
        if ($this->sentData) {
            return $this->sentData;
        }
        $contentTypes = $this->getHeader('content-type');
        $isFormEncoded = array_reduce(
            $contentTypes,
            function ($isFormEncoded, $contentType) {
                if ($isFormEncoded) {
                    return true;
                }

                return strpos($contentType, 'application/x-www-form-urlencoded') !== false
                    || strpos($contentType, 'multipart/form-data') !== false;
            },
            false
        );
        if ($isFormEncoded) {
            $this->sentData = $this->getParsedBody();
        } else {
            $this->sentData = json_decode((string)$this->getBody(), true);
        }

        return $this->sentData;
    }

    public function getFormat(): Format
    {
        return $this->format;
    }

    public function withFormat(Format $format): Request
    {
        return new static(
            $this->originalRequest,
            $this->internalUri,
            $this->originalPath,
            $this->resourceType,
            new Format((string)$format)
        );
    }

    public function withResourceType(ResourceType $resourceType): InterestRequestInterface
    {
        $clone = clone $this;
        $clone->resourceType = $resourceType;

        return $clone;
    }
}
