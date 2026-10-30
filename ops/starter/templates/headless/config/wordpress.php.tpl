<?php
declare(strict_types=1);
return [
  'mode' => 'headless',
  'theme' => [
    'parent' => 'gear-headless',
    'active' => 'gear-headless',
  ],
  'plugins' => [
    'active' => ['secure-passkeys', 'gear-headless-api', '__SLUG__-api'],
    'inactive' => [],
  ],
  'mu_plugins' => ['mood-wordpress-policies'],
  'policies' => [
    'password_login' => ['enabled' => true, 'roles' => ['administrator']],
    'application_passwords' => ['enabled' => true, 'allowed_roles' => []],
    'rest_api' => [
      'enabled' => true,
      'anonymous' => ['default' => 'deny', 'allow' => ['/gear/v1/health', '/__SLUG__/v1/example']],
    ],
  ],
];
