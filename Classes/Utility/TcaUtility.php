<?php

declare(strict_types=1);


namespace Pixelant\Interest\Utility;


use GeorgRinger\News\Hooks\BackendUtility;
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
}
