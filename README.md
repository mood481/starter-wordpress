# Mood WordPress Gear Starter

A reproducible WordPress starter for projects managed as buildable and deployable applications.

The repository contains application source and configuration. WordPress core and third-party packages are dependencies; CI builds and validates a self-contained release artifact before deployment.

## Requirements

Local maintenance requires:

- Git
- Docker

Local PHP, Composer and Deployer installations are not required.

## Main commands

```bash
./ops/test
./ops/smoke
./ops/audit
./ops/package
```

Dependency maintenance:

```bash
./ops/local/composer <command>
./ops/local/renovate dry-run
```

Deployment:

```bash
./ops/local/deployer install
./ops/local/deployer deploy
./ops/local/deployer rollback
./ops/local/deployer releases
```

## Repository model

```text
bootstrap/      WordPress entry points
config/         runtime configuration
src/            optional project-managed WordPress code
deploy/         Deployer recipes and target adapters
ops/            build, test, packaging and operational entry points
docs/           architecture and operational documentation
```

Generated directories such as `build/` and `dist/` must never be committed.

## Documentation

- [Architecture](docs/architecture.md)
- [Development](docs/development.md)
- [Deployment](docs/deployment.md)
- [CI/CD](docs/ci.md)
- [Hosting](docs/hosting.md)
