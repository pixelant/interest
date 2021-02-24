<?php
declare(strict_types=1);

namespace Pixelant\Interest\Configuration;

use Pixelant\Interest\Domain\Model\ResourceType;
use Psr\Log\InvalidArgumentException;

class ResourceConfiguration
{
    /**
     * @var ResourceType
     */
    private ResourceType $resourceType;

    /**
     * @var Access
     */
    private Access $read;

    /**
     * @var Access
     */
    private Access $write;

    /**
     * @var string
     */
    private string $handlerClass;

    /**
     * @var string[]
     */
    private array $aliases;

    /**
     * ResourceConfiguration constructor
     *
     * @param ResourceType $resourceType
     * @param Access       $read
     * @param Access       $write
     * @param string       $handlerClass
     * @param string[]     $aliases
     */
    public function __construct(
        ResourceType $resourceType,
        Access $read,
        Access $write,
        string $handlerClass,
        array $aliases,
    ) {
        $this->resourceType = $resourceType;
        $this->read = $read;
        $this->write = $write;
        $this->handlerClass = $handlerClass;
        $this->assertStringArray($aliases);
        $this->aliases = $aliases;
    }

    /**
     * @return ResourceType
     */
    public function getResourceType(): ResourceType
    {
        return $this->resourceType;
    }

    /**
     * @return Access
     */
    public function getRead(): Access
    {
        return $this->read;
    }

    /**
     * @return Access
     */
    public function getWrite(): Access
    {
        return $this->write;
    }

    /**
     * @return string
     */
    public function getHandlerClass(): string
    {
        return $this->handlerClass;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    private function assertStringArray(array $aliases)
    {
        foreach ($aliases as $alias) {
            if (!is_string($alias)) {
                throw new InvalidArgumentException('Only strings are allowed as aliases');
            }
        }
    }
}
