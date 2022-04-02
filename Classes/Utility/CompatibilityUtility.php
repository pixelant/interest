<?php

/** @noinspection ALL */

namespace Pixelant\Interest\Utility;

use Pixelant\Interest\Compatibility\Resource\Security\FileNameValidator as InterestFileNameValidator;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * Miscellaneous functions relating to compatibility with different TYPO3 versions
 *
 * @extensionScannerIgnoreFile
 */
class CompatibilityUtility
{
    /**
     * Return the application context
     *
     * @return \TYPO3\CMS\Core\Core\ApplicationContext
     */
    public static function getApplicationContext()
    {
        if (self::typo3VersionIsLessThan('10.2')) {
            // @phpstan-ignore-next-line
            return GeneralUtility::getApplicationContext();
        }

        return Environment::getContext();
    }

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
     * Dispatch an event as PSR-14 in TYPO3 v10+ and signal in TYPO3 v9.
     *
     * @param object $event
     * @return object
     */
    public static function dispatchEvent(object $event): object
    {
        if (self::typo3VersionIsLessThan('10')) {
            /** @var Dispatcher $signalSlotDispatcher */
            $signalSlotDispatcher = GeneralUtility::makeInstance(Dispatcher::class);

            $eventClassName = get_class($event);

            $signalSlotDispatcher->dispatch(
                $eventClassName,
                self::classNameToSignalName($eventClassName),
                [$event]
            );

            return $event;
        }

        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);

        return $eventDispatcher->dispatch($event);
    }

    /**
     * Register a PSR-14 event as a signal slot in TYPO3 v9.
     *
     * @param string $eventClassName
     * @param string $eventHandlerClassName
     */
    public static function registerEventHandlerAsSignalSlot(string $eventClassName, string $eventHandlerClassName)
    {
        if (self::typo3VersionIsGreaterThanOrEqualTo('10')) {
            return;
        }

        /** @var Dispatcher $signalSlotDispatcher */
        $signalSlotDispatcher = GeneralUtility::makeInstance(Dispatcher::class);

        $signalSlotDispatcher->connect(
            $eventClassName,
            self::classNameToSignalName($eventClassName),
            $eventHandlerClassName,
            '__invoke'
        );
    }

    /**
     * Returns "className" from "Foo\Bar\ClassName".
     *
     * @param string $className
     * @return string
     */
    protected static function classNameToSignalName(string $className)
    {
        $explodedFqcn = explode('\\', $className);

        return lcfirst(array_pop($explodedFqcn));
    }

    /**
     * @return InterestFileNameValidator|FileNameValidator
     */
    public static function getFileNameValidator(): object
    {
        if (self::typo3VersionIsLessThan('10.4')) {
            return GeneralUtility::makeInstance(InterestFileNameValidator::class);
        }

        return GeneralUtility::makeInstance(FileNameValidator::class);
    }
}
