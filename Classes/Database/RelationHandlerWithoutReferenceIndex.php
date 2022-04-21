<?php

declare(strict_types=1);

namespace Pixelant\Interest\Database;

use TYPO3\CMS\Core\Database\RelationHandler;

/**
 * Disables updating the reference index when handling relations.
 */
class RelationHandlerWithoutReferenceIndex extends RelationHandler
{
    /**
     * @var bool
     */
    protected $updateReferenceIndex = false;
}
