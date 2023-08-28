<?php

use Pixelant\Interest\Utility\CompatibilityUtility;

$ll = 'LLL:EXT:interest/Resources/Private/Language/locallang_db.xlf:';

$tca = [
    'ctrl' => [
        'title' => $ll . 'tx_interest_remote_id_mapping',
        'label' => 'remote_id',
        'type' => 'manual',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'rootLevel' => -1,
        'default_sortby' => 'ORDER BY uid',
        'enablecolumns' => [],
        'iconfile' => 'EXT:interest/Resources/Public/Icons/RemoteIdMapping.svg',
        'typeicon_column' => 'manual',
        'typeicon_classes' => [
            '0' => 'ext-interest-mapping',
            '1' => 'ext-interest-mapping-manual',
        ],
        'searchFields' => 'remote_id',
    ],
    'columns' => [
        'pid' => [
            'label' => 'pid',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'crdate' => [
            'label' => 'crdate',
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'tstamp' => [
            'label' => 'tstamp',
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'remote_id' => [
            'exclude' => false,
            'label' => $ll . 'tx_interest_remote_id_mapping.remote_id',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'unique,alphanum_x,trim',
                'required' => true,
            ],
        ],
        'table' => [
            'exclude' => false,
            'label' => $ll . 'tx_interest_remote_id_mapping.table',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'alphanum_x,trim',
                'required' => true,
            ],
        ],
        'uid_local' => [
            'exclude' => false,
            'label' => $ll . 'tx_interest_remote_id_mapping.local_uid',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'required' => true,
            ],
        ],
        'manual' => [
            'exclude' => false,
            'label' => $ll . 'tx_interest_remote_id_mapping.manual',
            'description' => $ll . 'tx_interest_remote_id_mapping.manual.description',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 1,
                'items' => [
                    [
                        'label' => '',
                        'value' => 0,
                    ],
                    [
                        'label' => '',
                        'value' => 1,
                    ],
                ],
            ],
        ],
        'metadata' => [
            'label' => 'Meta data',
            'config' => [
                'type' => 'passthrough',
                'default' => '',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'manual, remote_id, table, uid_local',
        ],
    ],
];

return $tca;
