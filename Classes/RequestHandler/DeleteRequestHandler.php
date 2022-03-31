<?php
declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;

class DeleteRequestHandler extends AbstractRecordRequestHandler
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
        (new DeleteRecordOperation(
            $remoteId,
            $language !== '' ? $language : null,
            $workspace !== '' ? $workspace : null
        ))();
    }
}
