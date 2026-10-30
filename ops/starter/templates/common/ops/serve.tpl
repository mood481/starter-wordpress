#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
ACTION="${1:-start}"
CONFIG="$ROOT/.env.serve.local"
if [[ -f "$CONFIG" ]]; then
  # Local project-owned configuration, never committed.
  set -a
  # shellcheck disable=SC1090
  source "$CONFIG"
  set +a
fi

export SMOKE_PORT="${SERVE_PORT:-8080}"
[[ "$SMOKE_PORT" =~ ^[0-9]+$ ]] && (( SMOKE_PORT >= 1 && SMOKE_PORT <= 65535 )) || {
  echo "SERVE_PORT must be an integer between 1 and 65535." >&2
  exit 2
}
slug="$(basename "$ROOT" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9-' '-')"
export COMPOSE_PROJECT_NAME="gear-local-$slug"
COMPOSE=(docker compose -f "$ROOT/ops/docker/smoke.compose.yml")

usage() {
  echo "Usage: ./ops/serve [start|stop|logs|reset|status]"
  echo "Starts a persistent local WordPress + MariaDB stack, using .env.serve.local."
}
case "$ACTION" in
  -h|--help) usage; exit 0;;
  start|stop|logs|reset|status) ;;
  *) usage >&2; exit 2;;
esac
command -v docker >/dev/null 2>&1 || { echo "Docker is required." >&2; exit 1; }
docker compose version >/dev/null

case "$ACTION" in
  stop) "${COMPOSE[@]}" down; exit 0;;
  logs) "${COMPOSE[@]}" logs -f app db; exit 0;;
  status) "${COMPOSE[@]}" ps; exit 0;;
  reset)
    echo "Removing local WordPress database and uploads; this is irreversible."
    "${COMPOSE[@]}" down --volumes --remove-orphans
    exit 0;;
esac

[[ -f "$ROOT/composer.lock" ]] || {
  echo "Missing composer.lock: first run ./ops/local/composer update --with-all-dependencies" >&2
  exit 1
}
# Rebuilding replaces bind-mounted files. Stop running containers first while
# keeping the named database/uploads volume intact.
echo "==> Stopping existing local containers (persistent data preserved)"
"${COMPOSE[@]}" down --remove-orphans
echo "==> Building WordPress"
"$ROOT/ops/build"
echo "==> Starting persistent WordPress and MariaDB containers"
"${COMPOSE[@]}" up -d --build --wait db app
"${COMPOSE[@]}" exec -T --user root app chown -R 33:33 /var/www/public/wp-content/uploads

# WP-CLI invocations must never allocate a pseudo-TTY: plugin activation
# happens from a redirected stdin, and local runs may be non-interactive.
if ! "${COMPOSE[@]}" run -T --rm cli core is-installed --path=/var/www/public/wp >/dev/null 2>&1; then
  echo "==> First local WordPress installation"
  "${COMPOSE[@]}" run -T --rm cli core install \
    --path=/var/www/public/wp \
    --url="http://127.0.0.1:$SMOKE_PORT" \
    --title="Local WordPress" \
    --admin_user=admin \
    --admin_password=local-wordpress-change-me \
    --admin_email=admin@example.test \
    --skip-email
  echo "Local-only admin credentials: admin / local-wordpress-change-me"
fi

state="$( "${COMPOSE[@]}" exec -T app php -r '
  $c = require "/var/www/config/wordpress.php";
  echo $c["theme"]["active"], PHP_EOL;
  foreach ($c["plugins"]["active"] ?? [] as $plugin) echo $plugin, PHP_EOL;
')"
theme="${state%%$'\n'*}"
[[ -n "$theme" ]] || { echo "Missing active theme in config/wordpress.php" >&2; exit 1; }
"${COMPOSE[@]}" run -T --rm cli theme activate "$theme" --path=/var/www/public/wp
while IFS= read -r plugin; do
  [[ -n "$plugin" ]] || continue
  "${COMPOSE[@]}" run -T --rm cli plugin activate "$plugin" --path=/var/www/public/wp
done <<< "${state#*$'\n'}"

echo "WordPress: http://127.0.0.1:$SMOKE_PORT"
echo "Admin:     http://127.0.0.1:$SMOKE_PORT/wp/wp-admin/"
echo "Stop:      ./ops/serve stop  |  Remove local data: ./ops/serve reset"
