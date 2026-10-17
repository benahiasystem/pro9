# Evidencia de cálculos exactos

Fecha: 16 de octubre de 2026. Complementa [implementación y pendientes](calculos_exactos_pro9.md).

**Actualización del 17 de octubre:** [extensión implementada y pendientes](calculos_exactos_extension_pro9.md), con [resultados y registros](evidencias/calculos_exactos_pro9/extension_2026_10_17/resultados.json). Suite actual: 813 pruebas PHP, 16.044 aserciones, cero fallos y 59 omisiones; 193 pruebas JavaScript sin fallos. MySQL temporal verificó cálculos, instalación/semillas/reversión/repetición del tenant e instalación/reversión/repetición central. Los recuentos siguientes corresponden a la primera entrega.

Los resultados siguientes acreditan los componentes y escenarios indicados. **No acreditan la migración completa de todas las operaciones de Pro9.** Las etapas 2–5 conservan los pendientes del informe. No se ejecutaron migraciones ni se modificaron datos de tenants reales.

## Ejecuciones verificadas

| Comprobación | Resultado |
| --- | --- |
| Suite PHP `Unit` | 795 pruebas descubiertas; 15.966 aserciones; cero fallos/errores; 58 omitidas |
| JavaScript `tests/js/*.test.cjs` | 180 pruebas; 180 aprobadas; cero fallos u omisiones |
| PHP: casos compartidos y cálculo fiscal de partidas | 15 pruebas; 61 aserciones |
| MySQL: exactitud, ciclo comercial y cobros concurrentes | 3 pruebas; 51 aserciones |
| MySQL: notas, devoluciones, packs, presentaciones, lotes, seriales e inventario | 4 pruebas; 81 aserciones |
| MySQL: instalación, semillas, rollback/repetición y órdenes de entrega | 2 pruebas; 1.614 aserciones |
| MySQL: nueva ejecución de exactitud y cuotas con participaciones periódicas persistidas | 1 prueba; 17 aserciones |
| MySQL: regresión de nota de crédito tras recuperar fracciones internas | 1 prueba; 25 aserciones |
| PDF, configuración y validación de entradas | 19 pruebas; 297 aserciones |
| Frontera HTTP de snapshots | 1 prueba; 19 aserciones |
| Sintaxis PHP de archivos nuevos/modificados, excluyendo Blade | 187 archivos; cero errores |
| Compilación Blade y sintaxis PHP resultante | 148 plantillas modificadas; cero errores |
| Inventario estático del esquema inicial | 111 tablas; 491 columnas comprobadas con `DECIMAL(30,12)` y snapshots; cero errores |
| Analizador sintáctico JavaScript/Vue de la auditoría | Cero errores de análisis |
| Validador `quick_validate.py` de la habilidad nueva | `Skill is valid!` |
| `git diff --check` | Sin errores |

Las filas se solapan: los subconjuntos unitarios están incluidos en la suite y no deben sumarse como pruebas independientes. La ejecución MySQL de notas incluye una versión anterior de la prueba del ciclo comercial; la ejecución posterior de aceptación verificó también las cantidades periódicas y el stock con las aserciones adicionales.

Las 58 pruebas omitidas de `Unit` requieren condiciones de integración específicas y **no cuentan como aprobadas**. Los escenarios MySQL de la tabla se activaron explícitamente y utilizaron bases temporales `pro9_fiscal_test_*`, con eliminación en la limpieza de cada caso. El transporte HKA utiliza dobles: no se emitieron comprobantes ni se contactó al proveedor.

La suite `Feature` no se acredita: su prueba de ejemplo depende de la base central `multifacturalo_dusk`, ausente en este entorno. No se creó esa base ni se modificó la configuración para ocultar esta limitación. PHP informa además la advertencia previa de parámetros opcionales de `Item.php` y la configuración PHPUnit antigua.

## Casos de aceptación

- Precio con IVA incluido: `500/1`, base `12500/29`, IVA `2000/29`; total cobrable `500.00`. Cien partidas y una cantidad de cien conservan `50000/1`.
- Cuotas de 100 y 1000 entre tres: reparto completo, orden estable de residuos. Descuento de uno entre tres: participaciones exactas y salida representada que conserva la suma.
- Cuotas de factura de 500 entre tres: snapshot con `scheduled_exact=500/3`, importes a céntimos que suman 500 y ajustes separados. La prueba adicional de MySQL vuelve a leer esas fracciones. La edición manual no reutiliza pesos automáticos obsoletos.
- Fracciones periódicas guardadas y recuperadas: se vuelve a operar desde numerador/denominador; las columnas de doce posiciones no se usan como autoridad. Cantidad `500/3` por precio 3 conserva 500; al editar el precio a 6 conserva 1000 y la reversión repone el stock.
- Conversión de ida/vuelta, precio neto con IVA y conversión de moneda, factores sin porcentaje y valores inferiores a un céntimo: siete casos compartidos entre PHP y JavaScript.
- Cobros: importe solicitado, principal convertido, IGTF y ajuste de liquidación separados; ausencia de ingresos ficticios; sumas de fracciones originales en agregados financieros.
- Notas y cobros: límites, rollback, idempotencia, bloqueo concurrente y reversión en los escenarios enumerados. Esto no sustituye la matriz de consumidores pendientes.
- PDF: opción desactivada y valores 2, 6 y 12; rechazo de 1, 13, fracciones, nulo y texto inválido. Tickets por defecto y `modern-2027` usan el precio del comprobante y mantienen sus entradas al renderizar. Las filas agrupadas conservan sus fracciones y calculan el promedio antes del formato.
- HTTP: se descartan snapshots de resultados del cliente en JSON, formularios y consultas; se mantienen entradas originales y el cuerpo sin procesar.

## Reproducción

Sin compilar assets:

```bash
docker compose exec -T php php vendor/bin/phpunit --testsuite Unit
node --test tests/js/*.test.cjs
docker compose exec -T php php vendor/bin/phpunit --filter 'ExactCalculationTest|FiscalLineCalculationTest'
docker compose exec -T php php vendor/bin/phpunit --filter 'CurrentPdfRenderingTest|PdfPricePrecisionTest|ExactInputValidationTest'
docker compose exec -T php php vendor/bin/phpunit --filter ExactCalculationHttpBoundaryTest
```

Integración optativa en bases MySQL temporales, usando el aislamiento de `FiscalEmissionSchemaTest`:

```bash
docker compose exec -T -e PRO9_FISCAL_MYSQL_TESTS=1 php php vendor/bin/phpunit --filter 'test_exact_calculations_survive_mysql_reads_grouping_discounts_and_rollback|test_exact_commercial_lifecycle_preserves_quantities_and_reverses_stock|test_concurrent_payments_igtf_and_retention_applications_are_serialized'
docker compose exec -T -e PRO9_FISCAL_MYSQL_TESTS=1 php php vendor/bin/phpunit --filter 'test_note_credit_limits_inventory_idempotency_and_hka_mapping|test_note_refund_preserves_collections_and_reverses_once|test_note_original_packs_presentations_lots_serials_and_stock_rollback'
docker compose exec -T -e PRO9_FISCAL_MYSQL_TESTS=1 php php vendor/bin/phpunit --filter 'test_fresh_tenant_migrations_seed_rollback_and_repeat|test_dispatch_multi_order_invoice_moves_only_the_pending_quantities'
```

Validación de habilidad y auditoría de operaciones restantes:

```bash
python3 /home/benahia/.codex/skills/.system/skill-creator/scripts/quick_validate.py .codex/skills/mantener-calculos-exactos-pro9
php scripts/audit_exact_calculations.php
node scripts/audit_exact_calculations.cjs
git diff --check
```

El inventario inicial recogía **1.057 candidatos PHP y 747 JavaScript**. El [inventario ampliado](calculos_exactos_extension_inventario.json) contiene 39.912 candidatos de aritmética, comparaciones, formato, controles y SQL: 123 tienen clasificación y evidencia individuales; 39.789 siguen pendientes. No son 39.789 errores monetarios confirmados. Los auditores ampliados analizan sin errores de sintaxis y ambos fallan en modo `--check` debido a esos pendientes. Permanecen consumidores de módulos secundarios, agregados SQL y exportaciones; el plan global sigue abierto.

## Evidencia visual

[Factura ficticia DEMO con precio unitario de doce posiciones](evidencias/calculos_exactos_pro9/precio_unitario_12.pdf) y [renderizado PNG](evidencias/calculos_exactos_pro9/precio_unitario_12.png). Se verificó con mPDF, extracción de texto y revisión visual: precio `116.123456789123`, base `100.10`, IVA `16.02` y total `116.12`. La base y el IVA representados suman el total, sin modificar sus fracciones persistentes.

[Resumen de resultados en JSON](evidencias/calculos_exactos_pro9/resultados.json), sin datos de tenants ni credenciales.

La compilación y aceptación visual del frontend corresponden al usuario según `frontend-build`. No se ejecutaron `npm run build`, Vite ni cambios en `public/build`. Las pruebas de JavaScript ejecutan la fuente y no acreditan un paquete de producción compilado.
