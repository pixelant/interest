<?php

declare(strict_types=1);

// phpcs:ignoreFile
$EM_CONF['interest'] = [
    'title' => 'Integration REST API',
    'description' => 'REST and CLI API for adding, updating, and deleting records in TYPO3. Tracks relations so records can be inserted in any order. Uses remote ID mapping so you don\'t have to keep track of what UID a record has gotten after import. Data is inserted using backend APIs as if a real human did it, so you can can inspect the record history and undo actions.',
    'version' => '4.1.0',
    'state' => 'stable',
    'category' => 'plugin',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.3.99',
        ],
    ],
];
