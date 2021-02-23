<?php
declare(strict_types=1);

namespace Pixelant\Interest;

use Cundd\Rest\Domain\Model\Format;
use Cundd\Rest\Http\ServerRequestProxyTrait;
use Pixelant\Interest\Domain\Model\ResourceType;
use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class Request implements InterestRequestInterface
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
     * Constructor for a new request with the given Server Request, resource type and format
     *
     * @param ServerRequestInterface $originalRequest
     * @param UriInterface $internalUri
     * @param string $originalPath
     * @param ResourceType $resourceType
     */
    public function __construct(
        ServerRequestInterface $originalRequest,
        UriInterface $internalUri,
        string $originalPath,
        ResourceType $resourceType
    ) {

        $this->originalRequest = $originalRequest;
        $this->originalPath = $originalPath;
        $this->resourceType = $resourceType;
        $this->internalUri = $internalUri;
    }
    /**
     * @return string
     */
    public function getPath(): string
    {
        // TODO: Implement getPath() method.
    }

    public function getOriginalRequest(): ServerRequestInterface
    {
        // TODO: Implement getOriginalRequest() method.
    }

    public function getResourceType(): ResourceType
    {
        // TODO: Implement getResourceType() method.
    }

    public function getSendData()
    {
        // TODO: Implement getSendData() method.
    }

    public function isWrite(): bool
    {
        // TODO: Implement isWrite() method.
    }

    public function isRead(): bool
    {
        // TODO: Implement isRead() method.
    }

    public function withResourceType(ResourceType $resourceType): InterestRequestInterface
    {
        // TODO: Implement withResourceType() method.
    }

    protected function setOriginalRequest(ServerRequestInterface $request)
    {
        // TODO: Implement setOriginalRequest() method.
    }
}
