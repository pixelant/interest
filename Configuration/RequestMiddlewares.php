<?php

$before = [
    'typo3/cms-frontend/backend-user-authentication',
];
$after = [];

if (\Pixelant\Interest\Utility\CompatibilityUtility::typo3VersionIsLessThan('10.0.0')) {
    $before[] = 'typo3/cms-frontend/site';
} else {
    $after[] = 'typo3/cms-frontend/site';
}

return [
    'frontend' => [
        'interest-rest-requests' => [
            'target' => Pixelant\Interest\Middleware\RequestMiddleware::class,
            'before' => $before,
            'after' => $after,
        ],
    ],
];
