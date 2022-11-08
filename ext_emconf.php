<?php
// phpcs:ignoreFile

$EM_CONF['interest'] = [
    'title' => 'Integration REST API',
    'description' => 'REST and CLI API for adding, updating, and deleting records in TYPO3. Tracks relations so records can be inserted in any order. Uses remote ID mapping so you don\'t have to keep track of what UID a record has gotten after import. Data is inserted using backend APIs as if a real human did it, so you can can inspect the record history and undo actions.',
    'version' => '1.1.2',
    'state' => 'stable',
    'category' => 'plugin',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.8-11.5.99',
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
