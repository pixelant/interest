<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'inteREST',
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
            'TTN\\Tea\\' => 'Classes/',
        ],
    ],
    'autoload-dev' => [
        'psr-4' => [
            'TTN\\Tea\\Tests\\' => 'Tests/',
        ],
    ],
];
