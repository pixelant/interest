<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException;
use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;

class CreateOrUpdateRequestHandler extends AbstractRecordRequestHandler
{
    /**
     * @inheritDoc
     */
    protected function handleSingleOperation(
        RecordRepresentation $recordRepresentation
    ): void {
        try {
            (new UpdateRecordOperation(
                $recordRepresentation,
                $this->metaData
            ))();
        } catch (NotFoundException $exception) {
            (new CreateRecordOperation(
                $recordRepresentation,
                $this->metaData
            ))();
        }
    }
}
