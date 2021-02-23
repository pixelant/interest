<?php
declare(strict_types=1);

namespace Pixelant\Interest\Http;

use Pixelant\Interest\Domain\Model\ResourceType;
use Psr\Http\Message\ServerRequestInterface;

interface InterestRequestInterface extends ServerRequestInterface
{
    /**
     * Returns the original request.
     *
     * @return ServerRequestInterface
     */
    public function getOriginalRequest(): ServerRequestInterface;

    /**
     * Returns request path (with mapped alias).
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Returns resource type, first argument after .../rest/ path.
     *
     * @return ResourceType
     */
    public function getResourceType(): ResourceType;

    /**
     * Returns sent data.
     *
     * @return mixed
     */
    public function getSendData();

    /**
     * Returns if request want to write data.
     *
     * @return bool
     */
    public function isWrite(): bool;

    /**
     * Returns if request want to read data.
     *
     * @return bool
     */
    public function isRead(): bool;

    /**
     * Returns instance with given resource type
     *
     * @param ResourceType $resourceType
     * @return $this
     */
    public function withResourceType(ResourceType $resourceType): self;
}
