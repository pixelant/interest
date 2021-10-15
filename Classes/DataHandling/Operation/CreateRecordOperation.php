<?php

declare(strict_types=1);


namespace Pixelant\Interest\DataHandling\Operation;

use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException;

/**
 * Handle a record creation operation.
 */
class CreateRecordOperation extends AbstractRecordOperation
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


    }

}
