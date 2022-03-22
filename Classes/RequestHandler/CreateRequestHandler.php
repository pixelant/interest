<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;

class CreateRequestHandler extends AbstractRecordRequestHandler
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
    ): void
    {
        new CreateRecordOperation(
            $data,
            $table,
            $remoteId,
            $language !== '' ? $language : null,
            $workspace !== '' ? $workspace : null,
            $this->metaData
        );
    }
}
