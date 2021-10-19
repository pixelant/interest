<?php
defined('TYPO3_MODE') or die('Access denied.');

(static function () {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][]
        = \Pixelant\Interest\Hook\ClearCachePostProc::class . '->clearCachePostProc';

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig(
        '@import \'EXT:interest/Configuration/TSconfig/User/setup.tsconfig\''
    );

    \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
        \Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent::class,
        \Pixelant\Interest\DataHandling\Operation\Event\Handler\StopIfRepeatingPreviousRecordOperation::class
    );

    \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
        \Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent::class,
        \Pixelant\Interest\DataHandling\Operation\Event\Handler\PersistFileDataEventHandler::class
    );

    \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
        \Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent::class,
        \Pixelant\Interest\DataHandling\Operation\Event\Handler\DeferSysFileReferenceRecordOperationEventHandler::class
    );

    \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
        \Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent::class,
        \Pixelant\Interest\DataHandling\Operation\Event\Handler\ProcessDeferredRecordOperationsEventHandler::class
    );
})();
