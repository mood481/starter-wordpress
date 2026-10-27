<?php

declare(strict_types=1);

return [
    // Headless is the minimal baseline. Set mode to 'standard' and explicitly
    // select a project theme when building a conventional WordPress frontend.
    'mode' => 'headless',

    // WordPress still requires an installed, valid theme. This one renders
    // no public site and is supplied as versioned project source code.
    'theme' => [
        'parent' => 'gear-headless',
        'active' => 'gear-headless',
    ],

    'plugins' => [
        'active' => ['secure-passkeys', 'gear-headless-api'],
        'inactive' => [],
    ],

    'mu_plugins' => ['mood-wordpress-policies'],

    'policies' => [
        'password_login' => [
            'enabled' => true,
            'roles' => ['administrator'],
        ],
        'application_passwords' => [
            'enabled' => true,
            'allowed_roles' => [],
        ],
        'rest_api' => [
            'enabled' => true,
            'anonymous' => [
                'default' => 'deny',
                // Only these endpoint paths are public; their callbacks
                // register GET methods and do not expose unpublished posts.
                'allow' => [
                    '/gear/v1/health',
                    '/gear/v1/posts',
                    '/gear/v1/posts/*',
                ],
            ],
        ],
    ],
];
