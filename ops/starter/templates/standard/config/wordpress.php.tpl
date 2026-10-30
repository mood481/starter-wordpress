<?php
declare(strict_types=1);
return [
  'mode' => 'standard',
  'theme' => [
    'parent' => 'twentytwentyfive',
    'active' => '__SLUG__',
  ],
  'plugins' => [
    'active' => ['secure-passkeys'],
    'inactive' => [],
  ],
  'mu_plugins' => ['mood-wordpress-policies'],
  'policies' => [
    'password_login' => ['enabled' => true, 'roles' => ['administrator']],
    'application_passwords' => ['enabled' => true, 'allowed_roles' => []],
    'rest_api' => [
      'enabled' => false,
      'anonymous' => ['default' => 'deny', 'allow' => []],
    ],
  ],
];
