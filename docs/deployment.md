# Deployment

## Overview

Deployment is based on Deployer 8 and on the release artifact produced by `ops/package`.

Deployer is the release lifecycle engine. Gear does not reimplement locking, release numbering, shared paths, activation, cleanup or rollback in shell scripts.

The deployment implementation is split into:

```text
deploy/
├── deploy.php
├── recipe/
│   └── wordpress-artifact.php
└── targets/
    ├── shared.php
    └── hostinger.php
```

## Artifact contract

Deployer receives an already built `.tar.gz` plus its `.sha256`.

The archive contains:

```text
config/
vendor/
public/
```

It does not contain runtime `.env`, database data, uploads, logs, cache, repository source or CI configuration.

`config/wordpress.php` is part of the artifact and declares application-level WordPress state that must match the active release.

## Remote release layout

Typical layout:

```text
<deploy-root>/
├── .dep/
├── releases/
├── shared/
│   ├── .env
│   └── public/wp-content/uploads/
└── current -> releases/<active>
```

The database is external persistent state and is never part of a release.

## WordPress state reconciliation

The deployment recipe reads desired WordPress state from:

```text
config/wordpress.php
```

The current implemented reconciliation covers the active theme.

For every relevant release transition it:

1. checks that WordPress is installed;
2. reads `theme.parent` and `theme.active`;
3. verifies that both themes exist in the release;
4. activates the configured active theme;
5. verifies the resulting WordPress `template` and `stylesheet` options.

This works whether the project uses Twenty Twenty-Five directly or an optional project child theme.

The reconciliation runs after install and normal deploy, and again after rollback so persistent database options remain aligned with the code release that is currently active.

Plugin activation is intentionally not implemented yet; the `plugins` declaration is reserved for the upcoming plugin baseline.

## Install

`install` bootstraps a new target.

Conceptually it:

1. prepares the Deployer directory structure;
2. prepares and persists the runtime `.env`;
3. generates missing WordPress authentication keys and salts;
4. creates and validates the first release;
5. links shared configuration and uploads;
6. activates the release;
7. publishes the target document root;
8. runs `wp core install` only if WordPress is not already installed;
9. sets `home`, `siteurl` and indexing visibility;
10. applies the desired WordPress theme state;
11. performs an HTTP health check.

A child theme is not required. A pristine starter installation activates Twenty Twenty-Five.

## Deploy

Normal deployment requires the remote shared `.env` to already exist.

It creates a new release from the validated artifact, activates it, reconciles WordPress state and runs the health check.

The same database, uploads and runtime configuration survive every release.

## Rollback

Rollback points `current` back to the previous Deployer release, reconciles the WordPress theme state from that release, and performs the application health check afterward.

A rollback changes application code and the application-controlled WordPress options required to match that code. It does not roll back general database content or other runtime state.

## Health check

The current application health check requests:

```text
WP_SITEURL + /wp-login.php
```

A successful 2xx/3xx response is required.

## Credentials

The generic Deployer runner expects an explicit SSH identity file.

Local execution points to an existing private key. CI materializes its SSH secret as a temporary private-key file and invokes the same runner.

## Deployment commands

```bash
./ops/deployer <task>
./ops/local/deployer <task>
./ops/ci/deployer <task>
```

The wrappers adapt configuration and credentials only. Deployment behavior remains in the Deployer recipes.
