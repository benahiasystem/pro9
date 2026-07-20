#!/bin/bash
#
# Elimina todos los archivos .pdf de storage/app/tenancy/tenants/*/pdf/
#
# Uso:
#   ./limpiar-pdfs-tenants.sh          # elimina los PDFs
#   ./limpiar-pdfs-tenants.sh --dry-run # solo muestra qué se eliminaría
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TENANTS_DIR="${SCRIPT_DIR}/storage/app/tenancy/tenants"
DRY_RUN=0

if [ "${1:-}" = "--dry-run" ]; then
    DRY_RUN=1
    echo "[DRY-RUN] No se eliminará ningún archivo."
    echo
fi

if [ ! -d "$TENANTS_DIR" ]; then
    echo "Error: no existe el directorio de tenants: $TENANTS_DIR"
    exit 1
fi

total_tenants=0
tenants_con_pdf=0
total_pdfs=0

for tenant_path in "$TENANTS_DIR"/*/; do
    [ -d "$tenant_path" ] || continue

    tenant_name="$(basename "$tenant_path")"
    pdf_dir="${tenant_path}pdf"
    total_tenants=$((total_tenants + 1))

    if [ ! -d "$pdf_dir" ]; then
        echo "[$tenant_name] Sin carpeta pdf — omitido"
        continue
    fi

    count=$(find "$pdf_dir" -maxdepth 1 -type f -iname '*.pdf' | wc -l)
    count=$(echo "$count" | tr -d ' ')

    if [ "$count" -eq 0 ]; then
        echo "[$tenant_name] 0 PDFs"
        continue
    fi

    tenants_con_pdf=$((tenants_con_pdf + 1))
    total_pdfs=$((total_pdfs + count))

    if [ "$DRY_RUN" -eq 1 ]; then
        echo "[$tenant_name] Se eliminarían $count PDF(s)"
    else
        find "$pdf_dir" -maxdepth 1 -type f -iname '*.pdf' -delete
        echo "[$tenant_name] Eliminados $count PDF(s)"
    fi
done

echo
echo "Tenants revisados: $total_tenants"
echo "Tenants con PDFs:  $tenants_con_pdf"
if [ "$DRY_RUN" -eq 1 ]; then
    echo "PDFs a eliminar:   $total_pdfs"
else
    echo "PDFs eliminados:   $total_pdfs"
fi
