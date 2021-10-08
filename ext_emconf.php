<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Integration REST API',
    'description' => 'REST API for read/write access to database',
    'version' => '1.0.0-alpha',
    'category' => 'plugin',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.8-10.4.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Pixelant\\Interest\\' => 'Classes/',
        ],
    ],
    'autoload-dev' => [
        'psr-4' => [
            'Pixelant\\Interest\\Tests\\' => 'Tests/',
        ],
    ],
];
