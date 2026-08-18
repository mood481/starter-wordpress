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
        ],

        'inactive' => [
            'bunnycdn',
            'cache-enabler',
        ],
    ],

    'mu_plugins' => [
        'mood-wordpress-policies',
    ],
];
