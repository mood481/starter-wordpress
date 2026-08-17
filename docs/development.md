# Development

## Local requirements

Local development and maintenance require only:

- Git
- Docker

The provided Docker wrappers use pinned Composer, Renovate and Deployer images.
`ops/test` always uses the Composer wrapper. `ops/build` and `ops/audit` use a
locally installed Composer when one is available and otherwise fall back to the
wrapper.

## Generated directories

The build and package output directories are generated and must remain outside
Git:

```text
build/
dist/
```

The top-level `vendor/` directory is also ignored defensively, although this
project installs dependencies in `build/vendor/`.

Runtime and local secret files matching `.env.*.local` must also remain
unversioned.

## Composer

Use the Docker wrapper:

```bash
./ops/local/composer validate
./ops/local/composer install
./ops/local/composer update
./ops/local/composer show --outdated
```

Composer is responsible for WordPress core, PHP dependencies and Composer-managed WordPress packages.

The configured vendor directory is inside `build/`, so dependency installation contributes directly to the generated build tree.

## Renovate

Create the local configuration from the example and provide the required tokens:

```bash
cp .env.renovate.example .env.renovate.local
```

Common commands:

```bash
./ops/local/renovate dry-run
./ops/local/renovate
LOG_LEVEL=debug ./ops/local/renovate dry-run
```

Renovate currently maintains Composer dependencies. It updates the lock file
without widening declared dependency ranges. Compatible updates are proposed
through pull requests, and automerge is disabled.

## Build and validation

The normal local validation flow is:

```bash
./ops/test
./ops/smoke
./ops/audit
./ops/package
```

### `ops/test`

Validates Composer metadata and the generated application structure.

It also builds the application, so running `ops/test` is the normal entry point before smoke testing and packaging.

### `ops/smoke`

Starts an isolated Docker runtime with WordPress and MariaDB and performs a real WordPress installation plus an HTTP login-page check.

The smoke environment mirrors the runtime filesystem contract:

```text
/var/www/
├── config/                     read-only
├── vendor/                     read-only
└── public/
    ├── index.php               read-only
    ├── wp-config.php           read-only
    ├── wp/                     read-only
    └── wp-content/
        ├── mu-plugins/         read-only
        ├── plugins/            read-only
        ├── themes/             read-only
        └── uploads/            writable disposable volume
```

The database and uploads volume are disposable and removed at the end of the smoke test.

### `ops/audit`

Runs Composer dependency auditing as an explicit operation.

Dependency updates do not implicitly run audit. This keeps dependency resolution and security reporting as separate concerns.

### `ops/package`

Packages the existing `build/` tree. It does not rebuild or modify it.

The output is a versioned `.tar.gz` release plus a SHA-256 checksum under `dist/`.

No `.env`, deployment credentials or runtime state are included.

## Local deployment

Local deployment uses Deployer through Docker.

A local deployment profile is loaded by:

```bash
./ops/local/deployer
```

The current profile file is `.env.install.local`.

It contains target connection information and, for first installation only, the initial WordPress administrator data and the path to the runtime environment file.

An explicit SSH identity file is required:

```dotenv
DEPLOY_SSH_KEY_FILE=/absolute/path/to/private-key
```

The private key itself must not be stored in the environment file.

Typical commands:

```bash
./ops/local/deployer install
./ops/local/deployer deploy
./ops/local/deployer rollback
./ops/local/deployer releases
```

`install` is for a new target. Normal releases use `deploy`.

## Runtime configuration

A deployment environment uses a local runtime configuration file, for example:

```text
.env.runtime.staging.local
.env.runtime.prod.local
```

It contains values such as:

```text
WP_ENVIRONMENT_TYPE
WP_HOME
WP_SITEURL
DB_*
AUTH_*
*_SALT
```

For a local initial installation, the deployment profile points to it through
`INSTALL_ENV_FILE`.

During a first installation, empty WordPress authentication keys and salts are generated before the runtime configuration is persisted remotely.

Subsequent deploys reuse the remote shared environment and do not replace it implicitly.
