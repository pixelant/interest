<?php

return [
    'interest:create' => [
        'class' => \Pixelant\Interest\Command\CreateCommandController::class,
        'schedulable' => false,
    ],
    'interest:delete' => [
        'class' => \Pixelant\Interest\Command\DeleteCommandController::class,
        'schedulable' => false,
    ],
    'interest:update' => [
        'class' => \Pixelant\Interest\Command\UpdateCommandController::class,
        'schedulable' => false,
    ],
    'interest:read' => [
        'class' => \Pixelant\Interest\Command\ReadCommandController::class,
        'schedulable' => false,
    ],
];
