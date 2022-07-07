<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling;

use Doctrine\DBAL\Exception\DeadlockException;
use Pixelant\Interest\Context;
use TYPO3\CMS\Core\DataHandling\DataHandler as Typo3DataHandler;

class DataHandler extends Typo3DataHandler
{
    /**
     * @var int
     * @see DataHandler::processClearCacheQueue()
     */
    private int $deadlockCount = 0;

    /**
     * @inheritDoc
     */
    public function updateRefIndex($table, $uid, ?int $workspace = null): void
    {
        if (Context::isDisableReferenceIndex()) {
            return;
        }

        parent::updateRefIndex($table, $uid);
    }

    /**
     * @inheritDoc
     * @throws DeadlockException
     */
    protected function processClearCacheQueue()
    {
        try {
            parent::processClearCacheQueue();
        } catch (DeadlockException $exception) {
            if ($this->deadlockCount > 10) {
                throw $exception;
            }

            $this->deadlockCount++;

            $this->processClearCacheQueue();
        }
    }
}
