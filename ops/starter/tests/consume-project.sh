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
TEMP="$(mktemp -d)"
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

echo "==> Build and validate the fresh consumer"
./ops/build
./ops/test

echo "==> Install and smoke-test the disposable WordPress runtime"
if [[ "$MODE" == headless ]]; then
    SMOKE_PROJECT_ROUTE="/$SLUG/v1/example" ./ops/smoke
else
    ./ops/smoke
fi

echo "==> Real $MODE consumer integration passed"
