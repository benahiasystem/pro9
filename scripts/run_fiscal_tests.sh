#!/usr/bin/env bash
# ########## INICIO CAMBIO POSTGRESQL 17
set -euo pipefail
cd "$(dirname "$0")/.."
exec php scripts/test_postgresql.php -- --printer 'Tests\Support\FiscalProgressPrinter' "$@" tests/Unit/FiscalEmissionSchemaTest.php
# ######### FIN CAMBIO POSTGRESQL 17
