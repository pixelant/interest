<?php

declare(strict_types=1);

namespace Pixelant\Interest\Domain\Model;

class ResourceType
{
    /**
     * @var string
     */
    private string $resourceType;

    /**
     * ResourceType constructor.
     * @param string $resourceType
     */
    public function __construct(string $resourceType)
    {
        $this->assertValidResourceType($resourceType);
        $this->resourceType = $resourceType;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->resourceType;
    }

    /**
     * @param string $resourceType
     * @throws \InvalidArgumentException
     */
    public static function assertValidResourceType(string $resourceType): void
    {
        if (!$resourceType instanceof self && !is_string($resourceType)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Resource Type must be of type string "%s" given',
                    is_object($resourceType) ? get_class($resourceType) : gettype($resourceType)
                )
            );
        }

        if (false !== strpos((string)$resourceType, '/')) {
            throw new \InvalidArgumentException('Resource Type must not contain a slash');
        }
    }
}
