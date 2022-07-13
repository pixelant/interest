<?php

declare(strict_types=1);

namespace Pixelant\Interest\Domain\Model\Dto;

use Pixelant\Interest\Utility\TcaUtility;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use Pixelant\Interest\Domain\Model\Dto\Exception\InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
