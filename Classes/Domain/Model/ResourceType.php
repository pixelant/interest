<?php
declare(strict_types=1);

namespace Pixelant\Interest\Domain\Model;

class ResourceType {

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
        $this->resourceType = $resourceType;
    }

    /**
     * @return string
     */
    public function getResourceType(): string
    {
        return $this->resourceType;
    }
}
