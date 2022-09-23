<?php

declare(strict_types=1);

namespace Pixelant\Interest\Domain\Model\Dto;

/**
 * DTO to handle record representation.
 */
class RecordRepresentation
{
    /**
     * @var array
     */
    protected array $data;

    /**
     * @var RecordInstanceIdentifier
     */
    protected RecordInstanceIdentifier $recordInstanceIdentifier;

    /**
     * @param array $data
     * @param RecordInstanceIdentifier $recordInstanceIdentifier
     */
    public function __construct(
        array $data,
        RecordInstanceIdentifier $recordInstanceIdentifier
    ) {
        $this->data = $data;
        $this->recordInstanceIdentifier = $recordInstanceIdentifier;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return RecordInstanceIdentifier
     */
    public function getRecordInstanceIdentifier(): RecordInstanceIdentifier
    {
        return $this->recordInstanceIdentifier;
    }
}
