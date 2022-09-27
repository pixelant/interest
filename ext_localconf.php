<?php

defined('TYPO3_MODE') or die('Access denied.');

(static function () {
    $GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['interest']
        = \Pixelant\Interest\Hook\ProcessCmdmap::class;

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig(
        '@import \'EXT:interest/Configuration/TSconfig/User/setup.tsconfig\''
    );

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update'][
        \Pixelant\Interest\Updates\RemovePendingRelationsWithEmptyRemoteIdUpdateWizard::IDENTIFIER
    ] = \Pixelant\Interest\Updates\RemovePendingRelationsWithEmptyRemoteIdUpdateWizard::class;


    if (\Pixelant\Interest\Utility\CompatibilityUtility::typo3VersionIsGreaterThanOrEqualTo('11')) {
        return;
    }

    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Imaging\IconRegistry::class
    );

    $iconRegistry->registerIcon(
        'ext-interest-mapping',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:interest/Resources/Public/Icons/RemoteIdMapping.svg']
    );

    $iconRegistry->registerIcon(
        'ext-interest-mapping-manual',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:interest/Resources/Public/Icons/ManualRemoteIdMapping.svg']
    );

    if (\Pixelant\Interest\Utility\CompatibilityUtility::typo3VersionIsGreaterThanOrEqualTo('10')) {
        return;
    }

    // TYPO3 v9 compatibility beyond this point.

    $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
    );

    $signalSlotDispatcher->connect(
        \TYPO3\CMS\Core\Resource\ResourceStorage::class,
        \TYPO3\CMS\Core\Resource\ResourceStorage::SIGNAL_PostFileDelete,
        \Pixelant\Interest\Slot\DeleteRemoteIdForDeletedFileSlot::class,
        '__invoke'
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
        \Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent::class,
        \Pixelant\Interest\DataHandling\Operation\Event\Handler\RelationSortingAsMetaDataEventHandler::class
    );

    \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
        \Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent::class,
        \Pixelant\Interest\DataHandling\Operation\Event\Handler\UpdateCountOnForeignSideOfInlineRecordEventHandler
            ::class
    );

    \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
        \Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent::class,
        \Pixelant\Interest\DataHandling\Operation\Event\Handler\ProcessDeferredRecordOperationsEventHandler::class
    );

    \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
        \Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEvent::class,
        \Pixelant\Interest\DataHandling\Operation\Event\Handler\ForeignRelationSortingEventHandler::class
    );

    \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
        \Pixelant\Interest\Router\Event\HttpRequestRouterMethodEvent::class,
        \Pixelant\Interest\Router\Event\Handler\RequestMethodEventHandler::class,
    );

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Console\CommandRequestHandler::class] = [
        'className' => \Pixelant\Interest\Console\OptimizedCommandRequestHandler::class,
    ];
})();
