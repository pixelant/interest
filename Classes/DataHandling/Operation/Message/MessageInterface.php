<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Message;

/**
 * Interface for internal messages between objects within a record operation's scope. For example transfer of data from
 * a BeforeRecordOperationEventHandler to an AfterRecordOperationEventHandler.
 */
interface MessageInterface
{
}
