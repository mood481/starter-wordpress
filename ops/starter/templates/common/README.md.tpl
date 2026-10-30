# __PROJECT_NAME__

WordPress Gear project (`__SLUG__`) generated for the `__LAYOUT__` layout.

## Requirements

Git and Docker with Docker Compose. PHP, Composer and Deployer do not need to
be installed on the host; the provided wrappers run them in containers.

## First local setup

The generator creates `.env.composer.local` (empty credentials) and
`.env.serve.local` (local port). Both are ignored by Git. Fill in
`GITEA_PACKAGE_USER` and `GITEA_PACKAGE_TOKEN` in
`.env.composer.local` with a **read-only** Gitea Packages token, or use
the `COMPOSER_AUTH` environment variable instead. Never commit credentials.

```sh
./ops/local/composer update --with-all-dependencies
./ops/serve start
```

The update creates `composer.lock`. **Commit that lock file.** The first
`serve start` builds the application, starts a local WordPress + MariaDB
environment and installs WordPress automatically. Local credentials are
`admin / local-wordpress-change-me` (for localhost only; change them).

Site: `http://127.0.0.1:8080`.
Admin: `http://127.0.0.1:8080/wp/wp-admin/`.

Change `SERVE_PORT` in `.env.serve.local` to use another local HTTP port.
Stop the local server before executing `./ops/smoke` on the same port.\nUse `./ops/serve stop` to stop without deleting local data,
`./ops/serve logs` to inspect containers, or
`./ops/serve reset` to delete the local database and uploads.

**Do not use the local development credentials or configuration in production.**
The local service is for development; `./ops/smoke` is an independent,
disposable test environment.

## Build, test and package

```sh
./ops/build       # Install locked dependencies and assemble build/
./ops/test        # Check Composer and build structure
./ops/smoke       # Disposable WordPress + MariaDB integration test
./ops/audit       # Composer dependency audit
./ops/package     # Produce a deployable dist/*.tar.gz and SHA-256 checksum
```

After changing dependencies, run `./ops/local/composer update` and commit
the updated lock. `build/` and `dist/` are generated, not versioned.

## Deployment

Application deployment uses Deployer 8 through Docker, but **your project
chooses when and where to run it**. No pipeline is imposed by the starter.

```sh
./ops/local/deployer install
./ops/local/deployer deploy
./ops/local/deployer releases
./ops/local/deployer rollback
```

For local invocation, start from
`docs/env.examples/env.install.example` to create
`.env.install.local` (ignored by Git), configure an SSH target and
a production runtime environment file. Keep real secrets outside Git.

Available Deployer adapters: `shared` (generic shared hosting) and
`hostinger`. Set `DEPLOY_ADAPTER` in your deployment configuration. The
artifact is the existing `dist/*.tar.gz` plus `.sha256`; it includes
WordPress core, resolved dependencies and project code but never your
database, uploads or runtime secrets. Deployer manages atomic release
activation, persistent state and rollback.

A CI system may run the same operations and provide credentials via its
secret store. A monorepo can orchestrate them from its root; this project
does not ship a CI pipeline.

## Project structure

- `src/plugins/`, `src/themes/`, `src/mu-plugins/`: project-owned WordPress code.
- `config/wordpress.php`: active theme, plugins and WordPress policies.
- `config/application.php`, `config/environments/`: runtime configuration.
- `bootstrap/`: versioned WordPress entrypoints.
- `ops/`: build, test, local server, package and Docker wrappers.
- `deploy/`: Deployer recipes and hosting adapters.
- `docs/env.examples/`: reference environment templates.
- `PROJECT.md`: generated project metadata and short initialization guide.

The MU-plugin loader in `src/mu-plugins/` loads the Composer dependency
`mood/wordpress-policies`; the implementation is not duplicated.
