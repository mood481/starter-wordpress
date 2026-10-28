# CI/CD and operational entry points

## Structure

- `ops/local/`: developer workstation wrappers for Composer, Renovate and Deployer (Docker-based).
- `ops/starter/`: one-shot project generation; never required for a deployed runtime.
- `ops/build`, `ops/test`, `ops/smoke`, `ops/audit`, `ops/package`: portable Gear application pipeline commands, shared by developers and CI.
- `ops/ci/`: provider-neutral CI credential/Deployer adapters.
- `deploy/`: Deployer recipes and hosting adapters; no CircleCI-specific logic.
- `.circleci/`: CI orchestration only.
- `.buildkite/`: legacy pipeline retained temporarily for comparison; not authoritative for CircleCI.

## First CircleCI validation

`.circleci/config.yml` intentionally runs only generator self-test, build, test and Docker Compose smoke.
It does not package, audit, deploy, roll back or publish any dependencies. The registry package remains in Gitea Packages.

Create a CircleCI project connected to GitHub `mood481/starter-wordpress`.
Use a Linux machine executor with Docker Engine and Docker Compose, because `ops/smoke` uses bind mounts.

Configure `COMPOSER_AUTH` as a masked project secret (or restricted context):
`{"http-basic":{"git.mood481.es":{"username":"...","password":"..."}}}`.
Do not commit the token; it is used only to read packages from Gitea.

The CircleCI run is complete only when generator, build, test and smoke steps succeed.
No CircleCI runtime has been exercised in this change.
