<?php

declare(strict_types=1);


namespace Pixelant\Interest\Utility;


use Pixelant\Interest\Domain\Repository\RemoteIdMappingRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TcaUtility
{
    /**
     * Returns true if the table is localizable.
     *
     * @param string $tableName
     * @return bool
     */
    public static function isLocalizable(string $tableName): bool
    {
        return self::getLanguageField($tableName) !== null;
    }

    /**
     * Returns the name of the table's localizable field.
     *
     * @param string $tableName
     * @return string|null
     */
    public static function getLanguageField(string $tableName): ?string
    {
        return $GLOBALS['TCA'][$tableName]['ctrl']['languageField'] ?? null;
    }

    /**
     * Returns the name of the field used by translations to point back to the original record, the record in the
     * default language of which they are a translation.
     *
     * @param string $tableName
     * @return string|null
     */
    public static function getTransOrigPointerField(string $tableName): ?string
    {
        return $GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'] ?? null;
    }

    /**
     * Returns the name of the field used by translations to point back to the original record (i.e. the record in any
     * language of which they are a translation).
     *
     * @param string $tableName
     * @return string|null
     */
    public static function getTranslationSourceField(string $tableName): ?string
    {
        return $GLOBALS['TCA'][$tableName]['ctrl']['translationSource'] ?? null;
    }

    /**
     * Returns TCA configuration for a field with type-related overrides.
     *
     * @param string $remoteId
     * @param string $table
     * @param string $field
     * @param array $row
     * @return array
     */
    public static function getTcaFieldConfigurationAndRespectColumnsOverrides(
        string $table,
        string $field,
        array $row,
        ?string $remoteId = null
    ): array
    {
        /** @var RemoteIdMappingRepository $mappingRepository */
        $mappingRepository = GeneralUtility::makeInstance(RemoteIdMappingRepository::class);

        $tcaFieldConf = $GLOBALS['TCA'][$table]['columns'][$field]['config'];

        if ($remoteId !== null && $mappingRepository->exists($remoteId)) {
            $row = array_merge(
                BackendUtility::getRecord(
                    $table,
                    $mappingRepository->get($remoteId())
                ),
                $row
            );
        }

        $recordType = BackendUtility::getTCAtypeValue($table, $row);

        $columnOverrideConfigForField
            = $GLOBALS['TCA'][$table]['types'][$recordType]['columnsOverrides'][$field]['config'] ?? null;

        if ($columnOverrideConfigForField !== null) {
            ArrayUtility::mergeRecursiveWithOverrule($tcaFieldConf, $columnOverrideConfigForField);
        }

        if ($tcaFieldConf === null) {
            throw new \UnexpectedValueException(
                'No configuration for the field "' . $table . '.' . $field . '".',
                1634895616563
            );
        }

        return $tcaFieldConf;
    }
}
