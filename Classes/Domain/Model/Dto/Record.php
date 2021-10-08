<?php

declare(strict_types=1);


namespace Pixelant\Interest\Domain\Model\Dto;

/**
 * A generic representation of a database record.
 */
class Record
{
    /**
     * The table name of the record.
     *
     * @var Table
     */
    protected $table;

    /**
     * @var string|null
     */
    protected $remoteId;

    /**
     * @var string|null
     */
    protected $uid;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * Record construct.
     *
     * @param Table $table
     * @param array $data
     * @param string|null $remoteId
     * @param string|null $uid
     * @throws MissingIdentifierException
     */
    private function __construct(Table $table, array $data, string $remoteId = null, string $uid = null)
    {
        if ($remoteId === null && $uid === null) {
            throw new MissingIdentifierException(
                'No remote ID or TYPO3 UID defined. Must have at least one of them to create a Record.',
                1633614946
            );
        }

        $this->table = $table;
        $this->data = $data;
        $this->remoteId = $remoteId;
        $this->uid = $uid;
    }

    /**
     * Record construct.
     *
     * @param Table $table
     * @param array $data
     * @param string|null $remoteId
     * @param string|null $uid
     * @throws MissingIdentifierException
     */
    public static function create(Table $table, array $data, string $remoteId = null, string $uid = null)
    {
        if ($remoteId !== null) {
            $record = $table->getRecordByRemoteId($remoteId);
        }

        if ($record === null && $uid !== null) {
            $record = $table->getRecordByUid($uid);
        }

        if ($record === null) {
            $record = new self($table, $data, $remoteId, $uid);
        }

        return $record;
    }

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * @return string|null
     */
    public function getRemoteId(): ?string
    {
        return $this->remoteId;
    }

    /**
     * @param string $remoteId
     */
    public function setRemoteId(string $remoteId)
    {
        $this->remoteId = $remoteId;
    }

    /**
     * @return string
     */
    public function getUid(): ?string
    {
        return $this->uid;
    }

    /**
     * @param string $uid
     */
    public function setUid(string $uid)
    {
        $this->uid = $uid;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
