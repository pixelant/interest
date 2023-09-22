<?php

declare(strict_types=1);

namespace Pixelant\Interest\DataHandling\Operation\Event\Handler;

use Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation;
use Pixelant\Interest\DataHandling\Operation\DeleteRecordOperation;
use Pixelant\Interest\DataHandling\Operation\Event\AbstractRecordOperationEvent;
use Pixelant\Interest\DataHandling\Operation\Event\RecordOperationEventHandlerInterface;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException;
use Pixelant\Interest\Utility\TcaUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Ensures that fields only have allowed data types. Arrays are relations, other fields are float, int, or string.
 * Fields with null values are unset.
 */
class SanitizeFieldValues implements RecordOperationEventHandlerInterface
{
    protected AbstractRecordOperation $recordOperation;

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    public function __invoke(AbstractRecordOperationEvent $event): void
    {
        if ($event->getRecordOperation() instanceof DeleteRecordOperation) {
            return;
        }

        $this->recordOperation = $event->getRecordOperation();

        foreach ($this->recordOperation->getDataForDataHandler() as $fieldName => $fieldValue) {
            if ($this->isRelationalField($fieldName)) {
                if (!is_array($fieldValue)) {
                    $fieldValue = GeneralUtility::trimExplode(',', $fieldValue, true);
                }

                $this->recordOperation->setDataFieldForDataHandler($fieldName, $fieldValue);

                continue;
            }

            if (is_float($fieldValue) || is_int($fieldValue) || is_string($fieldValue)) {
                continue;
            }

            throw new InvalidArgumentException(
                'Unsupported value type "' . get_debug_type($fieldValue) . '" in the field "' . $fieldName . '".',
                1695198635
            );
        }
    }

    /**
     * Specifies if field must be processed as relational or not.
     *
     * @param string $field
     * @return bool
     */
    protected function isRelationalField(string $field): bool
    {
        $settings = $this->recordOperation->getSettings();

        if (
            isset($settings['relationOverrides.'][$this->recordOperation->getTable() . '.'][$field])
            || isset($settings['relationOverrides.'][$this->recordOperation->getTable() . '.'][$field . '.'])
        ) {
            return (bool)$this->recordOperation->getContentObjectRenderer()->stdWrap(
                $settings['relationOverrides.'][$this->recordOperation->getTable() . '.'][$field] ?? '',
                $settings['relationOverrides.'][$this->recordOperation->getTable() . '.'][$field . '.'] ?? []
            );
        }

        return TcaUtility::isRelationalField(
            $this->recordOperation->getTable(),
            $field,
            $this->recordOperation->getDataForDataHandler(),
            $this->recordOperation->getRemoteId()
        );
    }
}
