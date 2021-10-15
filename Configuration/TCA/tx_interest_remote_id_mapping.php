<?php

$ll = 'LLL:EXT:interest/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $ll . 'tx_interest_remote_id_mapping',
        'label' => 'remote_id',
        'type' => 'manual',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'rootLevel' => -1,
        'default_sortby' => 'ORDER BY title',
        'enablecolumns' => [],
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
                'type' => 'passthrough'
            ]
        ],
        'crdate' => [
            'label' => 'crdate',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
            ]
        ],
        'tstamp' => [
            'label' => 'tstamp',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
            ]
        ],
        'remote_id' => [
            'exclude' => false,
            'label' => $ll . 'tx_interest_remote_id_mapping.remote_id',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'required,unique,alphanum_x,trim',
            ]
        ],
        'table' => [
            'exclude' => false,
            'label' => $ll . 'tx_interest_remote_id_mapping.table',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'required,alphanum_x,trim',
            ]
        ],
        'local_uid' => [
            'exclude' => false,
            'label' => $ll . 'tx_interest_remote_id_mapping.local_uid',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'required,int',
            ]
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
                        0 => '',
                        1 => '',
                    ],
                ],
            ]
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'manual, remote_id, table, local_uid'
        ],
    ],
];
