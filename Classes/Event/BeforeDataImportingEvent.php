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
     * @var string
     */
    protected string $remoteId;

    /**
     * BeforeDataImportingEvent constructor.
     * @param array $importData
     */
    public function __construct(array $importData, string $remoteId)
    {
        $this->importData = $importData;
        $this->remoteId = $remoteId;
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

    /**
     * @return string
     */
    public function getRemoteId(): string
    {
        return $this->remoteId;
    }

    /**
     * @param string $remoteId
     */
    public function setRemoteId(string $remoteId): void
    {
        $this->remoteId = $remoteId;
    }
}
