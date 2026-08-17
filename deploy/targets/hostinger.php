<?php

declare(strict_types=1);

namespace Deployer;

require __DIR__ . '/shared.php';

// Hostinger shared hosting prints an SSH MOTD/banner to stdout.
// Deployer expects clean stdout for internal probes such as test(),
// release_path detection and rsync-related operations.
// Running a clean Bash shell avoids loading the profile files that
// emit the banner.
set('shell', 'bash -norc -noprofile -ls');

set('target_placeholders', ['default.php']);
