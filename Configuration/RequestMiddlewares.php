<?php

use Pixelant\Interest\Middleware\RequestMiddleware;

return [
    'frontend' => [
        'interest-rest-requests' => [
            'target' => RequestMiddleware::class,
            'before' => ['typo3/cms-frontend/backend-user-authentication'],
            'after' => ['typo3/cms-frontend/site'],
        ],
    ],
];
