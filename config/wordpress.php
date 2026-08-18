<?php

declare(strict_types=1);

return [
    'theme' => [
        'parent' => 'twentytwentyfive',
        'active' => 'twentytwentyfive',
    ],

    'plugins' => [
        'active' => [
            'secure-passkeys',
            'cache-enabler',
        ],

        'inactive' => [
            'bunnycdn',
        ],
    ]
];
