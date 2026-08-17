# Architecture

## Purpose

WordPress Gear defines a common application model for new WordPress projects and for the progressive adoption of existing WordPress installations.

The architecture is intentionally independent from a specific hosting provider or CI/CD product.

## Core principles

- WordPress core is a dependency, not project source code.
- Composer resolves PHP and WordPress dependencies.
- `composer.lock` is always versioned and defines the versions actually deployed.
- Third-party plugins and themes are dependencies, not committed installed code.
- `src/` contains only code managed by the project.
- `build/` and `dist/` are generated and never versioned.
- Runtime state is separate from application releases.
- Production does not install dependencies or build application code.
- CI produces the artifact that is later deployed.
- Build, test, smoke, audit and package behavior belongs to `ops/`.
- Deployment lifecycle behavior belongs to Deployer recipes under `deploy/`.
- CI providers orchestrate those operations; they do not own application logic.
- Hosting providers are deployment targets, not part of the application architecture.

## Source, build and release

The source repository contains:

```text
bootstrap/      versioned WordPress entry points
config/         common and environment-aware runtime configuration
src/            optional project-managed plugins, themes and mu-plugins
deploy/         deployment recipes and target adapters
ops/            operational commands
docs/           project documentation
```

A build produces:

```text
build/
├── config/
├── vendor/
└── public/
    ├── index.php
    ├── wp-config.php
    ├── wp/
    └── wp-content/
        ├── mu-plugins/
        ├── plugins/
        └── themes/
```

`build/public/` is the application document root.

Packaging converts that build into a release archive under `dist/`. Packaging does not rebuild the application and does not include environment configuration, secrets, the database, uploads, logs or other runtime state.

The same release artifact must be deployable to different environments.

## WordPress layout

WordPress core and WordPress content are intentionally separated:

```text
public/
├── wp/              WordPress core
└── wp-content/      plugins, themes, mu-plugins and runtime uploads
```

The public site and the WordPress core URL are also intentionally distinct:

```text
WP_HOME=https://example.com
WP_SITEURL=https://example.com/wp
```

`WP_HOME` is the public site URL. `WP_SITEURL` is the URL where the WordPress core is exposed.

## Configuration

`bootstrap/wp-config.php` is a small versioned loader.

Effective configuration lives in:

```text
config/application.php
config/environments/
```

Deployment-specific values and secrets are provided at runtime through a `.env` file or environment variables and are never committed.

The native WordPress `WP_ENVIRONMENT_TYPE` value is the environment abstraction:

```text
development
staging
production
```

Common defaults should be safe for production. Environment files only override those defaults where required.

The eight WordPress authentication keys and salts are generated once when preparing a new runtime configuration and persist with that environment. They are not regenerated on deploy.

## Dependencies

`composer.json` expresses supported dependency ranges. `composer.lock` defines the deployed versions.

- The lock file pins the exact resolved versions.
- Compatible ranges allow dependency maintenance within the supported line.
- Major upgrades require manual review.
- Renovate maintains compatible dependency updates and lock-file changes.
- Dependency audit is an explicit operation and is not implicitly coupled to dependency updates.

The starter does not require WP Packages by default. It is added only when a project actually needs WordPress.org packages such as `wp-plugin/*` or `wp-theme/*`. WP Packages is therefore an upstream package provider, not part of the platform contract.

Premium packages may come directly from vendors or from private package infrastructure when required.

`src/` is optional. When present, the build copies its immediate `mu-plugins/`,
`plugins/` and `themes/` children into the corresponding WordPress content
directories. This is for code managed by the project rather than for Composer
dependencies.

## Runtime state

Deployment manages application code. Runtime state has a different lifecycle.

```text
application code     replaceable by deployment
database             persistent
uploads              persistent
cache                runtime/recreatable unless explicitly stated otherwise
logs                 runtime
```

Normal deployments must never replace the production database or uploads.

The deployment model keeps environment configuration and uploads under shared storage and links them into each release.

## Deployment boundary

Gear produces and validates the application artifact. Deployer manages release lifecycle and provider-specific publication.

```text
source
  ↓
build
  ↓
test / smoke / audit
  ↓
package
  ↓
release artifact
──────────────────────── application boundary
  ↓
Deployer
  ↓
target adapter
  ↓
hosting
```

Generic deployment behavior must not contain provider-specific branches. Provider differences belong in target adapters.

## Greenfield and brownfield

A new project and an adopted existing WordPress installation must eventually converge on the same runtime structure and release artifact.

Greenfield installation is part of the supported deployment lifecycle.

Brownfield adoption is a separate migration process. It may require project-specific work to inventory dependencies, move project code into `src/`, preserve the database and uploads, and align configuration. That one-time migration must not complicate the normal deploy path.

## Replaceability

Current implementations include Buildkite for CI/CD and Hostinger shared hosting as a deployment target.

Neither is an architectural dependency.

A future CI provider should only need to:

1. check out the repository;
2. run the `ops/` commands;
3. persist the package artifact;
4. provide deployment configuration and credentials;
5. invoke the Deployer runner.

A future hosting target should implement the deployment capabilities required by the relevant Deployer target adapter.
