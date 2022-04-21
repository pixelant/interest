<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling;

use Pixelant\Interest\Context;
use TYPO3\CMS\Core\DataHandling\DataHandler as Typo3DataHandler;

class DataHandler extends Typo3DataHandler
{
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
}
