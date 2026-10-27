# WordPress Gear profiles and API extension

## Profiles

`config/wordpress.php` declares `mode = headless | standard`. It also declares
the active theme, plugins and policies. The starter defaults to `headless`.

- **Headless**: `gear-headless` is a minimal valid theme. The active
  `gear-headless-api` plugin exposes `GET /wp-json/gear/v1/health`, plus a
  single `gear_headless_register_routes` hook for project plugins.
- **Standard**: a project-owned theme renders the public frontend. The API
  infrastructure plugin need not be active, and the anonymous REST policy
  defaults to native WordPress behavior. No third-party theme is required.

Gear does **not** own articles, authors, category resources, schema or SDKs.
Each consumer supplies its own project plugin and public route allowlist.

## Generate a consumer project

From a checkout of the starter, with Bash, Git and Docker available:

```sh
./ops/starter/create-project --mode headless --layout standalone \
  --slug editorial --name "Editorial" --target ../editorial-wp

./ops/starter/create-project --mode standard --layout monorepo \
  --slug magazine --name "Magazine" --target ../workspace/apps/magazine-wp
```

`standalone` copies the root CI example; `monorepo` does not modify its
parent repository, root package files or pipelines. All local commands work
from the generated WordPress project directory; monorepo CI should run them
with that directory as its working directory. The target must be nonexistent
or empty. The generator never runs Composer or changes the source starter.

Project generation does not initialize a Git repository or publish a package.
Each consumer owns versioning and delivery. Bootstrap a conventional theme
with `./ops/starter/bootstrap-theme <slug> "<name>"` when needed.

## Project API

The consuming headless project has a project plugin in `src/plugins/`. Register
public routes with WordPress APIs and a project-owned namespace:

```php
add_action('gear_headless_register_routes', static function (): void {
    register_rest_route('editorial/v1', '/articles', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => [new ArticlesController(), 'index'],
    ]);
});
```

Each endpoint owns its input validation, published-only queries and DTOs.
`wordpress-policies` restricts anonymous REST access to the allowlist
specified in `config/wordpress.php`. In headless mode, the default anonymous
allowlist contains only `/gear/v1/health`; the generator adds its single
project example route. A policy exception does not bypass the endpoint's
own permission callback. Authentication for WordPress administration remains
native.

`api.example.com` is a **deployment concern**, never a WordPress alias.
A reverse proxy or CDN may expose selected project endpoints, while
`wp.example.com` remains the WordPress canonical URL. This is compatible
with shared hosting (external proxy) and VPS (local reverse proxy).

Media offload, Bunny CDN, cache plugins, newsletters, search and SDKs are
**opt-in integrations per consumer**. They are intentionally not installed
as mandatory dependencies of Gear.

## Versions and validation

The supported PHP baseline is 8.4. The local smoke runtime runs with Docker;
no local PHP, Composer or Deployer binary is required.

After changing Composer constraints, resolve dependencies through
`./ops/local/composer update --with-all-dependencies`, commit the newly
generated `composer.lock`, then run:

```sh
./ops/build
./ops/test
./ops/smoke
./ops/package
./ops/starter/create-project --test
./ops/starter/bootstrap-theme --test
```

A stale Composer lock is not a deployable release. Note that the current
GitHub development branch of `wordpress-policies` is not the released
version from the private Composer registry; publish a tested release and
refresh the lock before relying on the extended method-level rules.
