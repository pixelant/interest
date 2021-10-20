<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation;

/**
 * Delete a record.
 */
class DeleteRecordOperation extends AbstractRecordOperation
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

        $this->dataHandler->cmdmap[$table][$this->getUid()]['delete'] = 1;

        $this->mappingRepository->remove($remoteId);
    }

}
