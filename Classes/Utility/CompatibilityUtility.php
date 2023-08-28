<?php

/** @noinspection ALL */

namespace Pixelant\Interest\Utility;

use TYPO3\CMS\Core\Utility\VersionNumberUtility;

/**
 * Miscellaneous functions relating to compatibility with different TYPO3 versions
 *
 * @extensionScannerIgnoreFile
 */
class CompatibilityUtility
{
    /**
     * Returns true if the current TYPO3 version is less than $version
     *
     * @param string $version
     * @return bool
     */
    public static function typo3VersionIsLessThan($version)
    {
        return self::getTypo3VersionInteger() < VersionNumberUtility::convertVersionNumberToInteger($version);
    }

    /**
     * Returns true if the current TYPO3 version is less than or equal to $version
     *
     * @param string $version
     * @return bool
     */
    public static function typo3VersionIsLessThanOrEqualTo($version)
    {
        return self::getTypo3VersionInteger() <= VersionNumberUtility::convertVersionNumberToInteger($version);
    }

    /**
     * Returns true if the current TYPO3 version is greater than $version
     *
     * @param string $version
     * @return bool
     */
    public static function typo3VersionIsGreaterThan($version)
    {
        return self::getTypo3VersionInteger() > VersionNumberUtility::convertVersionNumberToInteger($version);
    }

    /**
     * Returns true if the current TYPO3 version is greater than or equal to $version
     *
     * @param string $version
     * @return bool
     */
    public static function typo3VersionIsGreaterThanOrEqualTo($version)
    {
        return self::getTypo3VersionInteger() >= VersionNumberUtility::convertVersionNumberToInteger($version);
    }

    /**
     * Returns the TYPO3 version as an integer
     *
     * @return int
     */
    public static function getTypo3VersionInteger()
    {
        return VersionNumberUtility::convertVersionNumberToInteger(VersionNumberUtility::getNumericTypo3Version());
    }

    /**
     * Converts a TYPO3 v12 TCA array to one that is compatible with TYPO3 v11.
     *
     * @param array $tableTca The TCA array for a table. [ctrl => ..., ...]
     */
    public static function backportVersion12TcaFeaturesForTable(array $tableTca)
    {
        foreach ($tableTca['columns'] as &$fieldTca) {
            foreach ($fieldTca['config'] as $configurationProperty => $configurationValue) {
                switch ($configurationProperty) {
                    case 'items':
                        $items = [];

                        foreach ($configurationValue as $item) {
                            $items[] = [
                                $item['label'] ?? '',
                                $item['value'] ?? null,
                                $item['icon'] ?? null,
                                $item['group'] ?? null,
                                $item['description'] ?? null
                            ];
                        }

                        $fieldTca['config']['items'] = $items;

                        break;
                    case 'required':
                        if ($configurationValue === true) {
                            $fieldTca['config']['eval'] .= ',required';
                        }

                        unset($fieldTca['config']['required']);

                        break;
                    case 'type':
                        switch ($configurationValue) {
                            case 'datetime':
                                $fieldTca['config']['type'] = 'input';
                                $fieldTca['config']['renderType'] = 'inputDateTime';
                                break;
                            case 'number':
                                $fieldTca['config']['type'] = 'input';
                                $fieldTca['config']['eval'] .= 'int';
                                break;
                        }

                        break;
                }
            }
        }

        foreach ($current as $key => $value) {
            if (is_array($value)) {
                $recurseFunction($value, $current, $recurseFunction);
            } elseif ($key === 'required' && $value === true) {
                $parent['eval'] .= ',required';
                unset($parent['required']);
            }
        }
    }
}
