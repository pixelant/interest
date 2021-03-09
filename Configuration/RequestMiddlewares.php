<?php

return [
    'frontend' => [
        'interest-rest-requests' => [
            'target' => Pixelant\Interest\Middlewares\RequestMiddleware::class,
            'before' => [
                'typo3/cms-frontend/backend-user-authentication',
            ],
            'after' => [
                'typo3/cms-frontend/site'
            ]
        ],
    ]
];
