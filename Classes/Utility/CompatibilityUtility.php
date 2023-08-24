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
}
