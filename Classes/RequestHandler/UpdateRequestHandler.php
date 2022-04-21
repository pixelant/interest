<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;

class UpdateRequestHandler extends AbstractRecordRequestHandler
{
    /**
     * @inheritDoc
     */
    protected function handleSingleOperation(
        string $table,
        string $remoteId,
        string $language,
        string $workspace,
        array $data
    ): void {
        (new UpdateRecordOperation(
            $data,
            $table,
            $remoteId,
            $language !== '' ? $language : null,
            $workspace !== '' ? $workspace : null,
            $this->metaData
        ))();
    }
}
