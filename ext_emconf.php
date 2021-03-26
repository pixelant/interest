<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'REST API for Integrations',
    'description' => 'Integrate external data with TYPO3. Data is handled as a backend user. Logging, access permissions, and validity checks are the same as when a real user is editing/viewing data in the backend.',
    'version' => '1.0.0-alpha',
    'category' => 'plugin',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.6-10.4.99',
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
