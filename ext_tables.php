<?php

defined('TYPO3_MODE') or die();

call_user_func(
    function ($EXTKEY) {
        $GLOBALS['TYPO3_USER_SETTINGS']['columns']['token'] = array(
            'label' => 'LLL:EXT:inteREST/Resources/Private/Language/locallang_db.xlf:token',
            'type' => 'button',
            'clickData' => [
                'eventName' => 'setup:token:clicked'
            ],
            'table' => 'be_users',
        );

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToUserSettings('--div--;LLL:EXT:inteREST/Resources/Private/Language/locallang_db.xlf:token,token');
    },
    'inteREST'
);
