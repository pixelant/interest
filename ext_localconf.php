<?php
defined('TYPO3_MODE') or die('Access denied.');

(static function () {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc'][]
        = \Pixelant\Interest\Hook\ClearCachePostProc::class . '->clearCachePostProc';
})();
