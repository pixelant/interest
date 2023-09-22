<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException;

/**
 * Simply sets the language in the ContentObjectRenderer's data array.
 */
class SetContentObjectRendererLanguageEventHandler implements BeforeRecordOperationEventHandlerInterface
{
    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    public function __invoke(BeforeRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation()->getLanguage() === null) {
            $event->getRecordOperation()->getContentObjectRenderer()->data['language'] = null;
        } else {
            $event->getRecordOperation()->getContentObjectRenderer()->data['language']
                = $event->getRecordOperation()->getLanguage()->getHreflang();
        }
    }
}
