<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler\Message;

use Pixelant\Interest\DataHandling\Operation\Event\Handler\FilterPendingRelations;
use Pixelant\Interest\DataHandling\Operation\Event\Handler\PersistPendingRelationInformation;
use Pixelant\Interest\DataHandling\Operation\Message\RequiredMessageInterface;

/**
 * A message concerning pending relations to be persisted.
 *
 * @see FilterPendingRelations
 * @see PersistPendingRelationInformation
 */
class PendingRelationMessage implements RequiredMessageInterface
{
    private string $table;

    private string $field;

    /**
     * @var string[]
     */
    private array $remoteIds;

    /**
     * @param string $table
     * @param string $field
     * @param string[] $remoteIds The pointing remote IDs in a pending relation to record $uid in $field of $table.
     */
    public function __construct(string $table, string $field, array $remoteIds)
    {
        $this->table = $table;
        $this->field = $field;
        $this->remoteIds = $remoteIds;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return array
     */
    public function getRemoteIds(): array
    {
        return $this->remoteIds;
    }
}
