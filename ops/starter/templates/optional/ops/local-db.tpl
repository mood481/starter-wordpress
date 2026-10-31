#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
ACTION="${1:-}"
shift || true

usage() {
  cat <<'HELP'
Usage:
  ./ops/local-db export --output FILE.sql
  ./ops/local-db import --file FILE.sql --from-url URL --yes

Only the disposable local 'serve' database is accessed. No remote DB connection.
Import replaces local tables: --yes is required. Supply the original site's
URL so WP-CLI can replace it with the local URL after importing. Keep SQL dumps
outside the repository, as they may contain personal information.
HELP
}
fail() { echo "local-db: $*" >&2; exit 1; }
[[ "$ACTION" == export || "$ACTION" == import ]] || { usage >&2; exit 2; }

FILE="" FROM_URL="" APPROVE=false
while (($#)); do
  case "$1" in
    --output|--file|--from-url)
      (($#>=2)) || fail "Missing value for $1"
      case "$1" in
        --output|--file) FILE="$2";;
        --from-url) FROM_URL="$2";;
      esac
      shift 2;;
    --yes) APPROVE=true; shift;;
    --help|-h) usage; exit 0;;
    *) fail "Unknown option $1";;
  esac
done

[[ -n "$FILE" ]] || fail "--file or --output is required"
[[ "$FILE" != -* ]] || fail "File paths may not start with -"
case "$ACTION" in
  import)
    [[ -f "$FILE" && -r "$FILE" ]] || fail "SQL dump not readable: $FILE"
    [[ "$APPROVE" == true ]] || fail "Import replaces the local DB: pass --yes"
    [[ "$FROM_URL" =~ ^https?://[^[:space:]]+$ ]] || fail "--from-url must be the original http(s) site URL"
    ;;
  export)
    [[ ! -e "$FILE" ]] || fail "Refusing to overwrite existing file: $FILE"
    mkdir -p "$(dirname "$FILE")"
    ;;
esac
command -v docker >/dev/null 2>&1 || fail "Docker is required"

CONFIG="$ROOT/.env.serve.local"
if [[ -f "$CONFIG" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$CONFIG"
  set +a
fi
export SMOKE_PORT="${SERVE_PORT:-8080}"
slug="$(basename "$ROOT" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9-' '-')"
export COMPOSE_PROJECT_NAME="gear-local-$slug"
COMPOSE=(docker compose -f "$ROOT/ops/docker/smoke.compose.yml")
"${COMPOSE[@]}" ps --status running --services | grep -qx db || fail "Local DB is not running; start ./ops/serve first"

CLI=("${COMPOSE[@]}" run -T --rm --no-deps cli)
if [[ "$ACTION" == export ]]; then
  # WP-CLI emits the SQL to a file inside the container; stream it without
  # writing secrets to a volume or reusing an existing host filename.
  tmp="$(mktemp "$(dirname "$FILE")/.gear-export.XXXXXX")"
  trap 'rm -f "$tmp"' EXIT
  chmod 600 "$tmp"
  "${CLI[@]}" db export - --path=/var/www/public/wp > "$tmp" || fail "Export failed"
  [[ -s "$tmp" ]] || fail "Export returned an empty dump"
  mv "$tmp" "$FILE"
  trap - EXIT
  echo "Exported local SQL to $FILE"
  exit 0
fi

# Import: DB data originates from a trusted user-supplied SQL file.
# Save a local pre-import snapshot first; never overwrite an existing backup.
backup="$ROOT/.gear-local-backup-$(date +%Y%m%d-%H%M%S)-$$.sql"
umask 077
"${CLI[@]}" db export - --path=/var/www/public/wp > "$backup" || {
  rm -f "$backup"
  fail "Backup failed; no import attempted"
}
[[ -s "$backup" ]] || { rm -f "$backup"; fail "Empty backup; no import attempted"; }
echo "Pre-import backup: $backup"

# Avoid creating a file inside the container: WP-CLI reads SQL from stdin.
"${CLI[@]}" db import - --path=/var/www/public/wp < "$FILE" || fail "Import failed; restore the saved backup manually"

local_url="http://127.0.0.1:$SMOKE_PORT"
"${CLI[@]}" search-replace "$FROM_URL" "$local_url" --all-tables-with-prefix --precise --skip-columns=guid --path=/var/www/public/wp || fail "URL replacement failed; backup remains at $backup"
"${CLI[@]}" option update home "$local_url" --path=/var/www/public/wp
"${CLI[@]}" option update siteurl "$local_url/wp" --path=/var/www/public/wp
echo "Local database imported and URLs adjusted. Review users, content and privacy before development."
echo "Backup preserved at $backup"
