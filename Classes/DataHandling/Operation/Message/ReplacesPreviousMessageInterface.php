<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Message;

/**
 * Indicates that there should only be a zero or one message of each class with this interface in the queue at any given
 * time. Any previous message is removed and replaced with the new one.
 */
interface ReplacesPreviousMessageInterface extends MessageInterface
{
}
