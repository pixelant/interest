<?php

declare(strict_types=1);

namespace Pixelant\Interest\Event;

class BeforeDataImportingEvent
{
    /**
     * @var array
     */
    protected array $importData;

    /**
     * BeforeDataImportingEvent constructor.
     * @param array $importData
     */
    public function __construct(array $importData)
    {
        $this->importData = $importData;
    }

    /**
     * @return array
     */
    public function getImportData(): array
    {
        return $this->importData;
    }

    /**
     * @param array $importData
     */
    public function setImportData(array $importData): void
    {
        $this->importData = $importData;
    }

}
