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

# Read the entire declarative state in one process. The tab-delimited format
# avoids confusing the first plugin with the remaining lines.
state="$("${COMPOSE[@]}" exec -T app php -r '
  $config = require "/var/www/config/wordpress.php";
  $theme = $config["theme"]["active"] ?? "";
  if (!is_string($theme) || $theme === "") {
    fwrite(STDERR, "Missing theme.active in config/wordpress.php\n");
    exit(1);
  }
  echo "theme\t", $theme, PHP_EOL;
  foreach (["active" => "activate", "inactive" => "deactivate"] as $key => $action) {
    foreach ($config["plugins"][$key] ?? [] as $plugin) {
      if (!is_string($plugin) || $plugin === "") {
        fwrite(STDERR, "Invalid plugins.$key entry\n");
        exit(1);
      }
      echo $action, "\t", $plugin, PHP_EOL;
    }
  }
')"

echo "==> Reconciling WordPress theme and plugins"
while IFS=$'\t' read -r operation name; do
  [[ -n "$operation" ]] || continue
  case "$operation" in
    theme)
      "${COMPOSE[@]}" run -T --rm cli theme is-installed "$name" --path=/var/www/public/wp
      current="$("${COMPOSE[@]}" run -T --rm cli option get stylesheet --path=/var/www/public/wp)"
      if [[ "$current" != "$name" ]]; then
        "${COMPOSE[@]}" run -T --rm cli theme activate "$name" --path=/var/www/public/wp
      fi
      actual="$("${COMPOSE[@]}" run -T --rm cli option get stylesheet --path=/var/www/public/wp)"
      [[ "$actual" == "$name" ]] || { echo "Theme activation failed: $name" >&2; exit 1; }
      ;;
    activate)
      "${COMPOSE[@]}" run -T --rm cli plugin is-installed "$name" --path=/var/www/public/wp
      if ! "${COMPOSE[@]}" run -T --rm cli plugin is-active "$name" --path=/var/www/public/wp >/dev/null 2>&1; then
        echo "==> Activating plugin: $name"
        "${COMPOSE[@]}" run -T --rm cli plugin activate "$name" --path=/var/www/public/wp
      fi
      if ! "${COMPOSE[@]}" run -T --rm cli plugin is-active "$name" --path=/var/www/public/wp >/dev/null 2>&1; then
        echo "Expected plugin to be active: $name" >&2
        exit 1
      fi
      echo "Active plugin: $name"
      ;;
    deactivate)
      "${COMPOSE[@]}" run -T --rm cli plugin is-installed "$name" --path=/var/www/public/wp
      if "${COMPOSE[@]}" run -T --rm cli plugin is-active "$name" --path=/var/www/public/wp >/dev/null 2>&1; then
        echo "==> Deactivating plugin: $name"
        "${COMPOSE[@]}" run -T --rm cli plugin deactivate "$name" --path=/var/www/public/wp
      fi
      if "${COMPOSE[@]}" run -T --rm cli plugin is-active "$name" --path=/var/www/public/wp >/dev/null 2>&1; then
        echo "Expected plugin to be inactive: $name" >&2
        exit 1
      fi
      ;;
    *)
      echo "Unexpected state operation: $operation" >&2
      exit 1;;
  esac
done <<< "$state"

echo "WordPress: http://127.0.0.1:$SMOKE_PORT"
echo "Admin:     http://127.0.0.1:$SMOKE_PORT/wp/wp-admin/"
echo "Stop:      ./ops/serve stop  |  Remove local data: ./ops/serve reset"
