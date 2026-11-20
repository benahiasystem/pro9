# Precios e inventario de facturación — 17 de noviembre de 2026

Trabajo en `codex/postgresql-supabase-17-11`, worktree `/home/benahia/.codex/worktrees/pro9-postgresql-supabase-17-11/Pro9`. La rama incorpora `develop` hasta d13c5dfa. El objetivo completo permanece abierto; rendimiento POS aplazado. No se modificaron datos reales ni se crearon commits.

Se corrigió `item_unit_types.price_default`: era un selector signed tinyint con default 2, convertido por error a boolean. Ahora PostgreSQL conserva smallint, default 2 y CHECK -128..127. La referencia MySQL congelada no se cambió. Se fortaleció la comparación genérica de defaults para rechazar conversiones booleanas de valores distintos de 0/1.

El POS transporta precio efectivo y fracción original de catálogo, almacén y lista, sin convertirlos a float ni calcular desde céntimos. Conserva diferencias de precedencia entre endpoints. La selección de presentación mantiene el ID del padre; una edición manual sustituye explícitamente el precio original. La comprobación de stock suma unidades físicas de todas las partidas y envía su fracción, con validación de coincidencia de la proyección en el servidor. Se conservaron los límites comerciales de cada variante.

La factura resuelve la presentación por producto en el servidor y congela factor, composición y tipo individual/pack. La edición conserva la composición histórica y revierte el movimiento global verificado, los deltas realmente aplicados a lotes y el estado de seriales. Una creación fallida revierte toda la transacción. Las facturas de órdenes conservan sus cantidades, presentación, almacén, lotes y origen; sólo revierten su delta pendiente y evitan otra reversión genérica. Se limpian marcadores aportados por el cliente y se conserva la cabecera de origen desde el documento bloqueado. No se amplió el cálculo de otros módulos.

## Evidencia confirmada

- `precios_catalogo_pos_20261117_02`: HTTP real PostgreSQL → JSON → métodos Vue de las tres variantes → factura guardada, con 1/3, precios grandes, IDs adyacentes, IVA, monedas, listas, almacenes, barcode y variaciones. La tanda tuvo tres fallos posteriores de inventario global, corregidos después; no se presenta como aceptación global.
- `edicion_inventario_factura_20261117_08`: 21 pruebas/324 aserciones, sin fallos, fuente fija y limpieza temporal. Incluye ediciones repetidas, cambios del catálogo, NC, rollback y entradas de origen/seriales/almacenes inválidos. El ajuste posterior de serialización JSON de cabecera se comprueba también con origen consolidado no vacío en la aceptación ampliada.
- `formatos_develop_d13c5dfa_20261117_01.json`: las ocho plantillas A4/A5 conservan etiquetas, atributos y estilos de develop. Esta comprobación estática no sustituye inspección visual.
- `aceptacion_facturacion_pdf_20261117_01`: 97 pruebas/3.660 aserciones, una aserción antigua falló porque A5 usa «Productos» en el diseño de develop. El valor exacto estaba presente. La ruta HTTP y los casos de origen consolidado aprobaron. Se conservan fallo y advertencias detectadas.
- `javascript_facturacion_20261117_19`: 1.075 aprobadas y una fallida. Se corrigió el fallback de edición de cantidades de Garage/Fast; la regresión dirigida de POS y tasas pasó 43/43. La repetición 20, posterior a esa corrección, aprobó las 1.076 pruebas.

Las advertencias de importación provinieron de convertir mensajes de validación de API, que son mapas, a string. Se mantiene el mensaje público como texto, se atienden respuestas HTTP 422 sin registrarlas como éxito y se prueban los tres formatos existentes. No se cambiaron columnas, plantillas ni reglas de cobro del XLSX.

## Repeticiones posteriores confirmadas

- `aceptacion_facturacion_pdf_20261117_02`: 132 pruebas y 3.893 aserciones aprobadas, sin fallos ni omisiones; fuente fija y limpieza de las bases temporales confirmadas.
- `javascript_facturacion_20261117_20`: 1.076 aprobadas, sin fallos ni omisiones. La compilación autorizada `compilacion_worktree_20261117_05` aprobó con entradas sin cambios; Chrome `navegador_facturacion_20261117_18` completó 48 comprobaciones.
- `pdf_recursos_locales_20261117_02`: cuatro pruebas y 1.062 aserciones aprobadas. La corrección A5 sólo cambia el lector de cantidades en una celda existente; el arreglo de ruta de logo es exclusivo del fixture. `render_pdf_20261117_16` produjo 112 PDF y 120 páginas. La última revisión visual focal está en `revision_visual_pdf_recursos_20261117_01.json`, con sus diez páginas inspeccionadas y limitaciones explícitas.
- `formatos_develop_d13c5dfa_20261117_02.json`: las 66 plantillas documentales modificadas conservan etiquetas, atributos y CSS de develop. Las condiciones y cifras sí pueden afectar el resultado visual; no se afirma identidad píxel a píxel.

## Pendientes

Completar las auditorías estrictas de facturación y recoger el resultado terminal de la batería completa `pro9-pgtest-6fa227c14d19`, actualmente en ejecución sobre fuente fija. Reforzar los casos de respaldo identificados en `pendientes_respaldos_cobertura_20261117_01.json`. No se declara completa la implementación ni se trabaja en rendimiento POS.
