#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export SAB_FISCAL_MYSQL_TESTS=1
exec php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
    --printer 'Tests\Support\FiscalProgressPrinter' "$@" tests/Unit/FiscalEmissionSchemaTest.php
