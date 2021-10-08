<?php

declare(strict_types=1);


namespace Pixelant\Interest\Domain\Model\Dto;

/**
 * A representation of a database table.
 */
class Table
{
    /**
     * Holds existing objects.
     *
     * [table_name => Table]
     *
     * @var array
     */
    private static $objects = [];

    /**
     * The table name.
     *
     * @var string
     */
    protected $name;

    /**
     * Records in the table.
     *
     * @var Record[]
     */
    protected $records;

    /**
     * Construct for Table. Use Table::create() to create objects.
     *
     * @param string $name
     * @param array $records
     */
    private function __construct(string $name, array $records = [])
    {
        $this->name = $name;
        $this->records = $records;
    }

    /**
     * Create a Table object. Returns existing object if it exists.
     *
     * @param string $name
     * @param array $records
     */
    public static function create(string $name, array $records = [])
    {
        if (!key_exists($name, self::$objects)) {
            self::$objects[$name] = new self($name, $records);
        }

        return self::$objects[$name];
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return Record[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @param Record $record
     */
    public function addRecord(Record $record)
    {
        if (!in_array($record, $this->records, true)) {
            $this->records = $record;
        }
    }

    /**
     * Return a record by remote ID.
     *
     * @param string $remoteId
     * @return Record|null
     */
    public function getRecordByRemoteId(string $remoteId): ?Record
    {
        foreach ($this->getRecords() as $record) {
            if ($record->getRemoteId() === $remoteId) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Return a record by UID.
     *
     * @param string $uid
     * @return Record|null
     */
    public function getRecordByUid(string $uid): ?Record
    {
        foreach ($this->getRecords() as $record) {
            if ($record->getUid() === $uid) {
                return $record;
            }
        }

        return null;
    }
}
