#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd -P)"
MODE="${1:-}"

case "$MODE" in
    headless|standard) ;;
    *)
        echo "Usage: $0 headless|standard" >&2
        exit 2
        ;;
esac

command -v docker >/dev/null 2>&1 || {
    echo "Docker is required for the consumer integration test." >&2
    exit 1
}
docker compose version >/dev/null

# The generated project must be genuinely independent of the starter checkout.
# Docker Desktop on macOS may not share the system /private/var/folders path.
# Keep the disposable consumer alongside the checkout, outside the source
# tree (create-project deliberately rejects destinations inside the starter).
TEMP_PARENT="${GEAR_TEST_TMPDIR:-$(dirname "$ROOT")}"
[[ -d "$TEMP_PARENT" && -w "$TEMP_PARENT" ]] || {
    echo "Test directory not writable: $TEMP_PARENT" >&2
    echo "Set GEAR_TEST_TMPDIR to a Docker-shared writable directory." >&2
    exit 1
}
TEMP="$(mktemp -d "$TEMP_PARENT/.gear-consumer-test.XXXXXX")"
trap 'rm -rf "$TEMP"' EXIT
PROJECT="$TEMP/project"
SLUG="ci-$MODE"

echo "==> Generate a fresh $MODE consumer"
"$ROOT/ops/starter/create-project" \
    --mode "$MODE" \
    --layout standalone \
    --slug "$SLUG" \
    --name "CI $MODE Consumer" \
    --target "$PROJECT"

[[ ! -e "$PROJECT/composer.lock" ]] || {
    echo "Generated consumers must not inherit the starter lock." >&2
    exit 1
}

cd "$PROJECT"

# Explicitly check Docker's view: bind mounts can appear empty when their
# host path is outside Docker Desktop's shared directories.
docker run --rm \
    --volume "$PROJECT:/app:ro" \
    --workdir /app \
    --entrypoint sh \
    "${COMPOSER_IMAGE:-composer:2.10.2}" \
    -c 'test -f composer.json || { echo "Docker cannot see the generated composer.json at /app. Check Docker Desktop file sharing or GEAR_TEST_TMPDIR." >&2; exit 1; }'

echo "==> Resolve an independent Composer lock (PHP 8.4, Gitea Packages)"
./ops/local/composer update \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --with-all-dependencies

[[ -s composer.lock ]] || {
    echo "No consumer composer.lock was generated." >&2
    exit 1
}

# ops/package records the source revision. A newly generated standalone
# consumer owns its Git history and commits its resolved Composer lock.
echo "==> Initialize an independent consumer Git repository"
git init -q
git config user.name "Gear Consumer Integration"
git config user.email "gear-consumer@example.invalid"
git add --all
git commit -qm "test: initialize $MODE consumer with resolved dependencies"

echo "==> Build and validate the fresh consumer"
./ops/build
./ops/test

echo "==> Install and smoke-test the disposable WordPress runtime"
if [[ "$MODE" == headless ]]; then
    SMOKE_PROJECT_ROUTE="/$SLUG/v1/example" ./ops/smoke
else
    ./ops/smoke
fi

echo "==> Package and validate the consumer release artifact"
./ops/package

archive="$(find dist -maxdepth 1 -type f -name '*.tar.gz' -print -quit)"
[[ -n "$archive" && -f "$archive.sha256" ]] || {
    echo "Missing tar.gz release artifact or its checksum." >&2
    exit 1
}

(
    cd dist
    checksum="$(basename "$archive").sha256"
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum --check "$checksum"
    else
        shasum -a 256 --check "$checksum"
    fi
)

EXTRACTED="$TEMP/extracted"
mkdir -p "$EXTRACTED"
tar -xzf "$archive" -C "$EXTRACTED"

for path in \
    config/wordpress.php \
    vendor/autoload.php \
    public/index.php \
    public/wp-config.php \
    public/wp/wp-settings.php \
    public/wp-content/mu-plugins/mood-wordpress-policies.php; do
    [[ -f "$EXTRACTED/$path" ]] || {
        echo "Release artifact missing $path" >&2
        exit 1
    }
done

if [[ "$MODE" == headless ]]; then
    [[ -f "$EXTRACTED/public/wp-content/plugins/$SLUG-api/$SLUG-api.php" ]]
    [[ -f "$EXTRACTED/public/wp-content/plugins/gear-headless-api/gear-headless-api.php" ]]
    [[ -f "$EXTRACTED/public/wp-content/themes/gear-headless/style.css" ]]
else
    [[ -f "$EXTRACTED/public/wp-content/themes/$SLUG/style.css" ]]
    [[ -f "$EXTRACTED/public/wp-content/themes/twentytwentyfive/style.css" ]]
fi

# Contract test, not an SSH deployment: load both target adapters and verify
# their install/deploy/rollback/release commands exist in Deployer.
echo "==> Validate Deployer recipes for shared and hostinger"
for adapter in shared hostinger; do
    tasks="$(docker run --rm \
        --volume "$PROJECT:/app:ro" \
        --workdir /app \
        --env DEPLOY_HOST=example.invalid \
        --env DEPLOY_USER=ci \
        --env DEPLOY_ROOT=/srv/gear \
        --env DEPLOY_DOCUMENT_ROOT=/srv/public \
        --env "DEPLOY_ADAPTER=$adapter" \
        deployphp/deployer:v8.0.5 \
        -f deploy/deploy.php list --raw)"

    for task in install deploy rollback releases; do
        if ! printf '%s\n' "$tasks" | grep -Eq "^$task([[:space:]]|$)"; then
            echo "Deployer $adapter adapter missing task '$task'" >&2
            exit 1
        fi
    done
done

echo "==> Real $MODE consumer integration passed (package + Deployer contracts)"
