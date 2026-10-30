#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd -P)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
check() {
  local dest="$1" mode="$2" slug="$3"
  if command -v php >/dev/null 2>&1; then
    php -r '
      $c=require $argv[1]; $j=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
      $m=$argv[3];$s=$argv[4];
      function ensure(bool $ok): void { if (!$ok) { fwrite(STDERR,"Generator configuration mismatch\n"); exit(1); } }
      ensure($c["mode"]===$m); ensure($j["name"]==="mood/$s-wordpress");
      ensure($j["require"]["mood/wordpress-policies"]==="^0.2");
      ensure($c["theme"]["parent"]===($m==="standard"?"twentytwentyfive":"gear-headless"));
      if($m==="standard") {
        ensure($c["theme"]["active"]===$s);
        ensure($c["policies"]["rest_api"]["enabled"]===false);
        ensure(isset($j["require"]["wp-theme/twentytwentyfive"]));
      } else {
        ensure($c["policies"]["rest_api"]["enabled"]===true);
        ensure(in_array("$s-api",$c["plugins"]["active"],true));
      }
    ' "$dest/config/wordpress.php" "$dest/composer.json" "$mode" "$slug"
  elif command -v docker >/dev/null 2>&1; then
    docker run --rm -v "$dest:/project:ro" php:8.4-cli php -r '
      $c=require "/project/config/wordpress.php"; $j=json_decode(file_get_contents("/project/composer.json"),true,512,JSON_THROW_ON_ERROR);
      if($c["mode"]!==$argv[1] || $j["require"]["mood/wordpress-policies"]!=="^0.2") exit(1);
      if($argv[1]==="standard" && $c["policies"]["rest_api"]["enabled"]!==false) exit(1);
    ' "$mode"
  else
    echo "PHP or Docker required for generator tests" >&2; exit 1
  fi
}
for mode in headless standard; do
  for layout in standalone monorepo; do
    dest="$TMP/$mode-$layout"
    "$ROOT/ops/starter/create-project" --mode "$mode" --layout "$layout" --slug sample-project --name "Sample Project" --target "$dest"
    [[ -x "$dest/ops/build" && -f "$dest/config/wordpress.php" && -f "$dest/PROJECT.md" ]]
    [[ ! -e "$dest/composer.lock" && ! -e "$dest/ops/starter" && ! -e "$dest/.buildkite" ]]
    if [[ "$layout" == standalone ]]; then
      [[ -f "$dest/.circleci/config.yml" ]]
    else
      [[ ! -e "$dest/.circleci" ]]
    fi
    if [[ "$mode" == standard ]]; then
      [[ -f "$dest/src/themes/sample-project/style.css" ]]
      grep -Fq "Template: twentytwentyfive" "$dest/src/themes/sample-project/style.css"
      [[ ! -e "$dest/src/themes/gear-headless" ]]
    else
      [[ -f "$dest/src/plugins/sample-project-api/sample-project-api.php" ]]
      [[ -f "$dest/src/themes/gear-headless/style.css" ]]
    fi
    check "$dest" "$mode" sample-project
  done
done
mkdir "$TMP/occupied"; touch "$TMP/occupied/data"
if "$ROOT/ops/starter/create-project" --mode standard --layout standalone --slug safe --name Safe --target "$TMP/occupied" >/dev/null 2>&1; then
  echo "Unexpected overwrite" >&2; exit 1
fi
echo "Generator tests passed (four combinations)"
