# WordPress Gear project profiles

Create a consumer with a declarative overlay. Both modes work in standalone
repositories or as a self-contained subproject in a monorepo.

```sh
./ops/starter/create-project --mode headless --layout standalone --slug editorial --name "Editorial" --target ../editorial-wp
./ops/starter/create-project --mode standard --layout monorepo --slug magazine --name "Magazine" --target ../workspace/apps/magazine-wp
```

The generator copies only the paths listed in `ops/starter/manifests/`, then
renders the complete files from `ops/starter/templates/common/` and the
selected mode. No `sed`, `awk`, or PHP array patching is used. Only
`__SLUG__`, `__PROJECT_NAME__` and `__LAYOUT__` are substituted.

- Headless: `gear-headless` active, infrastructure plugin `gear-headless-api`,
  project-owned `<slug>-api` plugin and an anonymous REST read allowlist.
- Standard: Twenty Twenty-Five installed through Composer as the parent,
  with a minimal `<slug>` child theme active; anonymous REST policy disabled.
- Both use PHP 8.4, WordPress 7.1.x and `mood/wordpress-policies:^0.2`.
- Monorepo: the subproject does not carry a CircleCI config. Its parent
  repository is responsible for CI orchestration.

The generated project's lock is intentionally absent. Configure read-only
Gitea Packages Composer credentials, then update and commit the lock:

```sh
./ops/local/composer update --with-all-dependencies
./ops/build
./ops/test
./ops/smoke
```

The generator itself requires Bash and standard copy utilities, not PHP;
the generator tests use PHP 8.4 or Docker:

```sh
./ops/starter/tests/create-project.sh
```

The infrastructure plugin provides `GET /wp-json/gear/v1/health` and the
`gear_headless_register_routes` hook. A consumer owns its routes and models.
A reverse proxy or CDN for a separate public API domain is a deployment concern.
