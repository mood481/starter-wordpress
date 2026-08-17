# Hosting

## Hosting boundary

Hosting is a deployment target, not part of the Gear application model.

Gear does not attempt to provision every capability of a shared-hosting account. Infrastructure provisioning and application deployment are separate concerns.

For managed shared hosting, site creation, database creation, PHP selection, SSH enablement, TLS and provider-level backups may remain provider-managed or manually provisioned.

Once a target exists, Deployer manages application releases.

## Generic shared-hosting target

The generic shared-hosting adapter assumes the target provides enough capabilities for release deployment:

- SSH access;
- file upload over SSH;
- `tar`;
- `sha256sum` for release verification;
- symbolic links;
- filesystem rename/link operations;
- a PHP CLI suitable for WordPress;
- WP-CLI;
- a web-accessible document root;
- persistent database storage.

The deployment root and public document root may be different paths.

Generic release state lives below the deployment root. The target adapter publishes the active release into the provider document root.

Unknown existing content in the document root is treated as unsafe and blocks
first installation. The `.well-known` directory is the only preserved exception
for hosting-managed verification files.

## Hostinger shared hosting

Hostinger is the first validated shared-hosting adapter.

The application is deployed under a private deployment root, while Hostinger continues to serve its fixed `public_html` document root.

Example:

```text
domain-root/
├── .mws-wp/
│   ├── .dep/
│   ├── releases/
│   ├── shared/
│   └── current
└── public_html/
    ├── index.php
    ├── wp-config.php
    ├── wp
    └── wp-content
```

`public_html` contains links to the active release under `.mws-wp/current/public`.

Note the `.mws-wp` is a sample for locating the deployment artifacts.

### Hostinger adapter behavior

The Hostinger target currently contains only provider-specific behavior:

- `default.php` is recognized as the provider placeholder for a new site and may be removed during first publication.
- a clean Bash shell is used to suppress Hostinger's SSH banner/MOTD from stdout because Deployer internal probes require clean command output.
- the PHP CLI binary may be configured explicitly.

All other release behavior comes from the generic shared-hosting target and the WordPress artifact recipe.

### PHP CLI

Hostinger may run a different PHP version for SSH/CLI than the version selected for the website in hPanel.

The deployment profile can therefore set an explicit remote binary, for example:

```dotenv
DEPLOY_PHP_BINARY=/opt/alt/php83/usr/bin/php
```

This value is target configuration and does not belong in the WordPress runtime `.env`.

### What Gear does not provision on Hostinger

The current Hostinger adapter does not create or manage:

- the Hostinger hosting plan;
- domains or DNS;
- the website record in hPanel;
- the database or database user;
- PHP/FPM configuration in hPanel;
- SSH enablement;
- TLS certificates;
- provider backups.

Those capabilities are intentionally outside the application deployment contract.

The Hostinger API is not currently treated as a sufficient infrastructure abstraction for this starter.

## Other hosting models

A future shared-hosting provider should normally extend the generic `shared` target and add only its provider-specific differences.

A future VPS target is a separate concern. VPS infrastructure may be provisioned through OpenTofu/Terraform and configured with Ansible, while the same Gear artifact and Deployer release model can remain in use.

Moving to a VPS should be justified by operational or business requirements; Gear does not require one.

## Brownfield hosting adoption

Existing WordPress sites are not expected to fit the greenfield installation task automatically.

Adoption should preserve:

- the existing database;
- uploads;
- required project-specific configuration;
- application behavior during transition.

The objective is to migrate an existing site once into the standard Gear runtime model. After adoption, normal deploy and rollback behavior should be identical to a greenfield Gear project.
