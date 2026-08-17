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

`wordpress-artifact.php` contains application and WordPress behavior.

`shared.php` contains generic behavior for shared-hosting targets with a fixed document root.

Provider-specific differences belong in individual target adapters such as `hostinger.php`.

## Artifact contract

Deployer receives an already built `.tar.gz` plus its `.sha256`.

The archive contains:

```text
config/
vendor/
public/
```

It does not contain:

```text
.env
database
uploads
logs
cache
source repository
CI configuration
```

The artifact is verified before extraction.

## Remote release layout

Deployer owns a deployment root separate from the provider document root.

Typical layout:

```text
<deploy-root>/
├── .dep/
├── releases/
│   ├── 1/
│   ├── 2/
│   └── ...
├── shared/
│   ├── .env
│   └── public/wp-content/uploads/
└── current -> releases/<active>
```

Each release receives links to the shared runtime configuration and uploads.

The database is external persistent state and is never part of a release.

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
10. performs an HTTP health check.

Installation is intentionally defensive. It must not silently overwrite unknown content in the hosting document root.

## Deploy

Normal deployment does not receive or replace runtime configuration.

It requires the remote shared `.env` to already exist, creates a new release from the CI/local artifact, activates it and runs the health check.

```text
artifact
  ↓
verify
  ↓
new release
  ↓
shared links
  ↓
current switch
  ↓
target publication
  ↓
health check
```

The same database, uploads and runtime configuration survive every release.

## Rollback

Rollback uses Deployer's release history.

It points `current` back to the previous Deployer release and performs the application health check afterward.

A rollback changes application code only. It does not roll back database changes or runtime state.

Projects that later introduce schema/data migrations must define their own compatibility and rollback policy before such migrations become part of deployment.

## Health check

The current application health check requests:

```text
WP_SITEURL + /wp-login.php
```

A successful 2xx/3xx response is required.

The health check is deliberately small. More application-specific checks may be added by projects without changing the generic release lifecycle.

## Credentials

The generic Deployer runner expects an explicit SSH identity file.

Local execution points to an existing private key.

CI materializes its SSH secret as a temporary private-key file and then invokes the same runner.

Deployer itself therefore uses the same authentication model in every execution environment.

## Deployment commands

Generic runner:

```bash
./ops/deployer <task>
```

Local adapter:

```bash
./ops/local/deployer <task>
```

CI adapter:

```bash
./ops/ci/deployer <task>
```

The wrappers adapt configuration and credentials only. Deployment behavior remains in the Deployer recipes.
