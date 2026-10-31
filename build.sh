#!/usr/bin/env bash
# Builds the release zips WHMCS owners upload: the module folder, plus README, CHANGELOG and LICENSE.
#
#   ./build.sh netarz      build/netarz-ai-whmcs-<version>.zip  (module netarz_ai — the original edition)
#   ./build.sh gisoo       build/gisoo-ai-whmcs-<version>.zip   (module gisoo_ai — the Gisoo edition)
#   ./build.sh all         both
#   ./build.sh test-gisoo  run the whole PHPUnit suite against the Gisoo edition
#
# One source, two editions: editions/gisoo/make-gisoo.php renames the module, namespace,
# functions and tables, and switches Brand::EDITION. Both editions use the same API, key and credit.
set -euo pipefail

cd "$(dirname "$0")"
VERSION=$(grep -oE "VERSION = '[^']+'" modules/addons/netarz_ai/lib/Schema.php | cut -d"'" -f2)
PHP_BIN=${PHP_BIN:-php}
edition="${1:-netarz}"

stage() {
  rm -rf build/stage && mkdir -p build/stage
  cp -R modules build/stage/
  find build/stage -name '.DS_Store' -delete
}

case "$edition" in
  netarz)
    stage
    cp README.md LICENSE CHANGELOG.md build/stage/
    OUT="build/netarz-ai-whmcs-${VERSION}.zip"
    ;;
  gisoo)
    stage
    cp CHANGELOG.md build/stage/
    "$PHP_BIN" editions/gisoo/make-gisoo.php build/stage
    cp README.md LICENSE build/stage/
    OUT="build/gisoo-ai-whmcs-${VERSION}.zip"
    ;;
  all)
    bash "$0" netarz
    bash "$0" gisoo
    exit 0
    ;;
  test-gisoo)
    rm -rf build/test-gisoo && mkdir -p build/test-gisoo
    cp -R modules tests composer.json phpunit.xml build/test-gisoo/
    cp -R vendor build/test-gisoo/
    "$PHP_BIN" editions/gisoo/make-gisoo.php build/test-gisoo
    (cd build/test-gisoo && composer dump-autoload -q && "$PHP_BIN" -d memory_limit=512M vendor/bin/phpunit --exclude-group live)
    rm -rf build/test-gisoo
    exit 0
    ;;
  *)
    echo "usage: $0 [netarz|gisoo|all|test-gisoo]" >&2
    exit 64
    ;;
esac

rm -f "$OUT"
(cd build/stage && zip -qrX "../$(basename "$OUT")" .)
rm -rf build/stage
echo "$OUT"
