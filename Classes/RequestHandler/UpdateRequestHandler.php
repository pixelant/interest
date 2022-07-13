<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\DataHandling\Operation\UpdateRecordOperation;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;

class UpdateRequestHandler extends AbstractRecordRequestHandler
{
    /**
     * @inheritDoc
     */
    protected function handleSingleOperation(
        RecordRepresentation $recordRepresentation
    ): void {
        (new UpdateRecordOperation(
            $recordRepresentation,
            $this->metaData
        ))();
    }
}
