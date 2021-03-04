<?php

$tca = [
    'ctrl' => [
        'title' => 'Test',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'default_sortby' => 'title',
        'iconfile' => 'EXT:tea/Resources/Public/Icons/Record.svg',
        'searchFields' => 'title',
    ],
    'interface' => [
        'showRecordFieldList' => 'title, relationField1, relationField2',
    ],
    'types' => [
        '1' => ['showitem' => 'title, relationField1, relationField2'],
    ],
    'columns' => [
        'title' => [
            'label' => 'title',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'relation_field1' => [
            'label' => 'relationField1',
            'config' => [
                'type' => 'select',
                'rows' => 8,
                'cols' => 40,
                'default' => 0,
                'foreign_table' => 'tx_interest_test_table2'
            ],
        ],
        'relation_field2' => [
            'label' => 'relationField2',
            'config' => [
                'type' => 'select',
                'rows' => 8,
                'cols' => 40,
                'default' => 0,
                'foreign_table' => 'tx_interest_test_table2'
            ],
        ],
    ],
];

return $tca;
