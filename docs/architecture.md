# Architecture

## Purpose

WordPress Gear defines a common application model for new WordPress projects and for the progressive adoption of existing WordPress installations.

The architecture is intentionally independent from a specific hosting provider or CI/CD product.

## Core principles

- WordPress core is a dependency, not project source code.
- Composer resolves PHP and WordPress dependencies.
- `composer.lock` is always versioned and defines the versions actually deployed.
- Third-party plugins and themes are dependencies, not committed installed code.
- Project-managed plugins, themes and mu-plugins live under `src/`.
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
config/         runtime and desired WordPress state
src/            project-managed plugins, themes and mu-plugins
deploy/         deployment recipes and target adapters
ops/            operational commands and starter scaffolding
docs/           project documentation
```

`src/` is source code and must remain versioned. Empty source directories may contain `.gitkeep` until a project adds its own code.

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

## Runtime configuration

`bootstrap/wp-config.php` is a small versioned loader.

Effective runtime configuration lives in:

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

## Desired WordPress state

Versioned WordPress state that belongs to the application, rather than to the runtime environment, is declared in:

```text
config/wordpress.php
```

The current contract declares:

```text
theme.parent
theme.active
plugins
```

The starter itself is valid without project-specific source code and therefore defaults to:

```text
theme.parent = gear-headless
theme.active = gear-headless
```

A project may optionally replace the active theme with its own child theme.

The theme state is enforced by smoke tests and deployment. Plugin state is reserved for the plugin baseline and will follow the same model.

## Theme model

Gear Headless is a minimal project-owned baseline theme, with no third-party theme dependency.

The starter contains only a minimal non-rendering theme. Projects using a conventional WordPress frontend must explicitly provide a theme.

`create-project --mode standard` selects a Composer-managed Twenty Twenty-Five parent and a project-owned child theme. `create-project --mode headless` selects Gear Headless. The project generator uses profile templates rather than modifying PHP configuration with shell text replacements.

Reusable application behavior should normally live in plugins or mu-plugins rather than in a shared intermediate child theme.

## Dependencies

`composer.json` expresses supported dependency ranges. `composer.lock` defines the deployed versions.

- The lock file pins the exact resolved versions.
- Compatible ranges allow dependency maintenance within the supported line.
- Major upgrades require manual review.
- Renovate maintains compatible dependency updates and lock-file changes.
- Dependency audit is an explicit operation and is not implicitly coupled to dependency updates.

The standard consumer profile uses WP Packages for Twenty Twenty-Five; the headless baseline has no third-party theme dependency. The repository is scoped to WordPress package types such as `wp-theme/*` and `wp-plugin/*`.

WP Packages is an upstream package provider, not part of the Gear platform contract.

During build, immediate children of `src/mu-plugins/`, `src/plugins/` and `src/themes/` are copied into the corresponding WordPress content directories. Build collisions with Composer-installed packages are rejected.

## Build and validation contract

The release flow is explicit:

```text
build
  ↓
test
  ↓
smoke
  ↓
package
```

Each operation has one responsibility:

```text
build       produces build/
test        validates the generated build statically
smoke       validates the build in a disposable WordPress runtime
package     packages the existing build without rebuilding it
```

The starter itself is validated with the minimal Gear Headless theme.

Generator templates are tested with `./ops/starter/tests/create-project.sh` across four profile/layout combinations.

Audit is intentionally independent from the release chain.

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

Greenfield installation selects a complete theme strategy through the headless or standard project profile.

Brownfield adoption is a separate migration process. It may require project-specific work to inventory dependencies, move project code into `src/`, preserve the database and uploads, and align configuration. That one-time migration must not complicate the normal deploy path.

## Replaceability

Current implementations include Buildkite for CI/CD and Hostinger shared hosting as a deployment target.

Neither is an architectural dependency.
