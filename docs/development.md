# Development

## Local requirements

Local development and maintenance require only:

- Git
- Docker

A local PHP or Composer installation is not required.

The local wrappers provide pinned Composer, Renovate and Deployer environments. Build and test may use a locally available Composer executable when present and otherwise fall back to the Docker Composer wrapper.

## Source and generated directories

Project-managed source lives under:

```text
src/
├── mu-plugins/
├── plugins/
└── themes/
```

These directories are versioned and must not be ignored. Empty directories may contain `.gitkeep`.

Generated output remains outside Git:

```text
build/
dist/
```

The top-level `vendor/` directory is also ignored defensively, although this project installs dependencies in `build/vendor/`.

Runtime and local secret files matching `.env.*.local` must remain unversioned.

## Composer

Use the Docker wrapper when invoking Composer directly:

```bash
./ops/local/composer validate
./ops/local/composer install
./ops/local/composer update
./ops/local/composer show --outdated
```

Composer is responsible for WordPress core, PHP dependencies and Composer-managed WordPress packages.

The configured vendor directory is inside `build/`, so dependency installation contributes directly to the generated build tree.

The headless starter uses a minimal versioned theme in `src/themes/gear-headless/`; no third-party theme is installed by default.

## Renovate

Create the local configuration from the example and provide the required tokens:

```bash
cp docs/env.examples/env.renovate.example .env.renovate.local
```

Common commands:

```bash
./ops/local/renovate dry-run
./ops/local/renovate
LOG_LEVEL=debug ./ops/local/renovate dry-run
```

Renovate currently maintains Composer dependencies. Compatible updates are proposed through pull requests, and automerge remains disabled until CI status reporting back to Gitea is available.

## Starter operations

Starter-specific one-shot operations live under:

```text
ops/starter/
```

They are distinct from `ops/local/`, which contains wrappers for operational tools executed from a local workstation.

### Project generation

The generator at `ops/starter/create-project` composes common files and complete profile templates. `ops/starter/templates/` holds the declarative configurations and theme/API code; `ops/starter/manifests/` lists reusable paths. `ops/starter/tests/create-project.sh` validates four combinations. Twenty Twenty-Five and the child theme are selected together in the standard profile; no separate theme bootstrap is required.

## Desired WordPress state

The starter default is:

```php
return [
    'theme' => [
        'parent' => 'gear-headless',
        'active' => 'gear-headless',
    ],
    'plugins' => [],
];
```

In a standard consumer, `theme.parent = twentytwentyfive` and `theme.active = <slug>`. In headless, both are `gear-headless`. Plugins are explicitly declared for each profile.

## Build and validation

The normal local release-validation flow is:

```bash
./ops/build
./ops/test
./ops/smoke
./ops/package
```

Dependency audit is independent:

```bash
./ops/audit
```

### `ops/build`

Builds the application into `build/`.

It:

- removes the previous build;
- installs dependencies using `composer.lock`;
- creates the WordPress content structure;
- copies the bootstrap and configuration files;
- copies project-managed code from `src/`.

It is the only release-validation script that creates or replaces `build/`.

### `ops/test`

Statically validates an existing build.

It does not build the application. If `build/` is missing, run `./ops/build` first.

### `ops/smoke`

Starts an isolated Docker runtime with WordPress and MariaDB and performs a real WordPress installation.

Smoke reads the expected parent and active theme from `config/wordpress.php`, verifies that both are installed, activates the configured theme, verifies the resulting `template` and `stylesheet` WordPress options, and checks the WordPress login page over HTTP.

The smoke test checks the active theme declared in `config/wordpress.php`. The database and uploads volume are disposable and removed at the end of the smoke test.

### `ops/audit`

Runs Composer dependency auditing as an explicit operation.

### `ops/package`

Packages the existing `build/` tree. It does not rebuild or modify it.

The output is a versioned `.tar.gz` release plus a SHA-256 checksum under `dist/`.

No `.env`, deployment credentials or runtime state are included.

## Local deployment

Local deployment uses Deployer through Docker.

Typical commands:

```bash
./ops/local/deployer install
./ops/local/deployer deploy
./ops/local/deployer rollback
./ops/local/deployer releases
```

`install` is for a new target. Normal releases use `deploy`.
