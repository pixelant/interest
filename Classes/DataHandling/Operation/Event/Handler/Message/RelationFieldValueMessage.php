<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler\Message;

use Pixelant\Interest\DataHandling\Operation\Event\Handler\RegisterValuesOfRelationFields;
use Pixelant\Interest\DataHandling\Operation\Message\RequiredMessageInterface;

/**
 * The value of a foreign relation field.
 *
 * @see RegisterValuesOfRelationFields
 */
class RelationFieldValueMessage implements RequiredMessageInterface
{
    private string $table;

    private string $field;

    /**
     * @var int|string
     */
    private $id;

    /**
     * @var int|string|float|array
     */
    private $value;

    /**
     * @param string $table
     * @param string $field
     * @param int|string $id
     * @param int|string|float|array $value
     */
    public function __construct(string $table, string $field, $id, $value)
    {
        $this->table = $table;
        $this->field = $field;
        $this->id = $id;
        $this->value = $value;
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
     * @return int|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int|string|float|array
     */
    public function getValue()
    {
        return $this->value;
    }
}
