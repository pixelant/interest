<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;

class CreateOrUpdateRequestHandler extends AbstractRecordRequestHandler
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
        try {
            new UpdateRecordOperation(
                $data,
                $table,
                $remoteId,
                $language !== '' ? $language : null,
                $workspace !== '' ? $workspace : null,
                $this->metaData
            );
        } catch (NotFoundException $exception) {
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
}
