# Changelog

Significant changes to Mood WordPress Gear Starter are recorded here.
**Git tags** (`vX.Y.Z` and `vX.Y.Z-<prerelease>`) are the authoritative
starter versions. This repository does not maintain a separate version file.
The WordPress themes and generated consumer projects have independent versions.

## [Unreleased]

### Added
- Declarative templates for headless and standard WordPress projects,
  supporting standalone repositories and monorepos.
- PHP 8.4 and WordPress application baselines with `mood/wordpress-policies` 0.2.
- CircleCI verification of both generated consumer profiles with disposable
  WordPress and MariaDB installations.
- Consumer artifact validation: `ops/package` archive, SHA-256 checksum,
  extracted release structure and Deployer task loading for both target adapters.

### Changed
- Generated projects own their CI/CD configuration; neither profile copies
  the starter's CircleCI pipeline.
- Conventional WordPress projects include a Twenty Twenty-Five child theme,
  while headless projects use Gear's minimal theme and API extension.

### Notes
- `ops/package` and the Deployer recipes are reusable **application**
  build/deployment contracts, not a distribution mechanism for the starter.
- Application publishing, deployment triggers and target infrastructure belong
  to each generated consumer.
