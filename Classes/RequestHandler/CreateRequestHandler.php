<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\DataHandling\Operation\CreateRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;

class CreateRequestHandler extends AbstractRecordRequestHandler
{
    /**
     * @inheritDoc
     */
    protected function handleSingleOperation(
        RecordRepresentation $recordRepresentation
    ): void {
        (new CreateRecordOperation(
            $recordRepresentation,
            $this->metaData
        ))();
    }
}
