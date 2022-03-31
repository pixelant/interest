<?php

declare(strict_types=1);

namespace Pixelant\Interest\Command;

/**
 * Command for reading data from a record.
 */
class ReadCommandController extends AbstractRecordCommandController
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();

        $this->setDescription('Read data from a record. NOT YET IMPLEMENTED');
    }
}
