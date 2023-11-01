<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Message;

/**
 * A message that must be retrieved. If left in the queue when the record operation completes, the record operation
 * must throw an exception. Good to ensure that all required actions are taken.
 */
interface RequiredMessageInterface extends MessageInterface
{
}
