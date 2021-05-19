<?php

declare(strict_types=1);

namespace Pixelant\Interest\Event;

class BeforeDataHandlingEvent
{
    /**
     * @var array
     */
    protected array $data;

    /**
     * BeforeDataHandlingEvent constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
