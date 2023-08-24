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
})();
