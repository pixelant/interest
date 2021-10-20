<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\Handler\Exception\ConflictException;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Performs an update operation on a record.
 */
class UpdateRecordOperation extends AbstractRecordOperation
{
    public function __construct(
        array $data,
        string $table,
        string $remoteId,
        ?string $language = null,
        ?string $workspace = null,
        ?array $metaData = []
    ) {
        parent::__construct($data, $table, $remoteId, $language, $workspace, $metaData);

        $this->dataHandler->datamap[$table][$this->getUid()] = $this->getData();
    }
}
