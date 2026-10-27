<?php

declare(strict_types=1);

return [
    // Supported deployment-independent profiles: headless and standard.
    'mode' => 'headless',

    // A minimal valid theme is necessary even when WordPress renders no site.
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
                // Public routes are explicitly registered by the Gear API
                // and project plugins. Methods are restricted by the policy.
                'allow' => ['/gear/v1/health'],
            ],
        ],
    ],
];
