<?php

$tca = [
    'ctrl' => [
        'title' => 'Test2',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
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
        'field1' => [
            'label' => 'LLL:EXT:tea/Resources/Private/Language/locallang_db.xlf:tx_tea_domain_model_product_tea.description',
            'config' => [
                'type' => 'text',
                'rows' => 8,
                'cols' => 40,
                'max' => 2000,
                'eval' => 'trim',
            ],
        ],
        'field2' => [
            'label' => 'LLL:EXT:tea/Resources/Private/Language/locallang_db.xlf:tx_tea_domain_model_product_tea.image',
            'config' => [
                'type' => 'text',
                'rows' => 8,
                'cols' => 40,
                'max' => 2000,
                'eval' => 'trim',
            ],
        ],
    ],
];

return $tca;
