# Headless profile

Gear's starter baseline is headless. A minimal versioned theme is kept under
`src/themes/gear-headless` because WordPress requires a valid active theme.
It does **not** depend on Twenty Twenty-Five or any other third-party theme.

The `gear-headless-api` plugin exposes only GET endpoints:

- `/wp-json/gear/v1/health`
- `/wp-json/gear/v1/posts` (published posts, pagination bounded to 50)
- `/wp-json/gear/v1/posts/{id}` (published posts only)

The WordPress native REST API remains available internally to authenticated
editors. `mood/wordpress-policies` is configured via `config/wordpress.php`
to deny anonymous REST routes other than the explicitly allowlisted paths.
New projects should add domain-specific endpoints without modifying that
general security package. The outer `api.` host must additionally restrict
requests at the web-server/proxy layer.

The plugin's endpoints deliberately do not implement media storage, newsletters,
public search or CDN rewrites. Those are project integrations.

To build a conventional WordPress frontend, switch `mode` to `standard`,
explicitly select and install a valid theme, and revise the REST exposure policy.
Gear does not implicitly install a third-party theme.

Local operations still require **only Git and Docker**, not locally installed PHP:
`./ops/build`, `./ops/test`, `./ops/smoke` and `./ops/package`.

Before any release run
`./ops/local/composer update --with-all-dependencies` to regenerate and
commit `composer.lock` after changing PHP or WordPress version constraints.
A release must not proceed with a stale lock file.

For shared hosting use the existing `shared` target adapter if the provider
supports SSH, symlinks, the required PHP CLI and a persistent database.
The presence of a headless mode does not imply a VPS or Docker in production.
