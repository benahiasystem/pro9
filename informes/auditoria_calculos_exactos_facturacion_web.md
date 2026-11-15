# Auditoría de cálculos exactos de facturación Web

Fecha: 14 de noviembre de 2026. Alcance: formularios Web de factura, nota de venta, cotización y notas de crédito/débito; conversiones entre ellos y sus reportes. La revisión se realizó sobre el árbol de trabajo existente, que contenía cambios sin confirmar. No se modificó lógica de producción ni datos reales.

## Resultado y matriz de cobertura

La exactitud integral de los cinco tipos y todos sus reportes **no está certificada**. Se confirmaron defectos en cotizaciones y Tensu y existe una prueba de PDF fallida. «Verificado» se refiere sólo al escenario y nivel indicados.

| Área | Estado | Evidencia y límites |
| --- | --- | --- |
| Motor decimal PHP/JS | Verificado parcialmente | Casos unitarios de fracciones, precio con IVA, descuentos, reparto y conversiones; no acreditan automáticamente todos los consumidores. |
| Factura Web estándar | Pendiente de comprobación integral | Se observó un borrador sin guardar en navegador y existen pruebas focales de cálculo; faltan recorridos completos de todas las variantes, guardado y reportes. |
| Factura Web Tensu | Defecto confirmado | La suma visible y el pago sugerido pierden precisión en el caso descrito abajo. El método `submit` retorna antes de guardar. |
| Nota de venta Web | Pendiente de comprobación integral | Las pruebas y lecturas focales no acreditan todas las entradas, edición, conversiones y reportes. `sale_notes/partials/item.vue:1438` convierte la cantidad a `parseFloat`; su efecto al guardar requiere reproducción. |
| Cotización Web, ambos formularios | Defecto confirmado | Suma binaria rechaza un pago válido y al preparar partidas se pierde precisión de precios grandes. |
| Nota de crédito | Pendiente de comprobación integral | Las pruebas aisladas cubren límites y fracciones en algunos escenarios; resta acreditar exhaustivamente parciales, anulaciones y flujos Web. |
| Nota de débito | Pendiente de comprobación integral | Existen pruebas fiscales y de ajuste; no se acredita la totalidad del recorrido Web ni de los reportes. |
| Reportes de documentos, clientes y notas de venta | Verificado parcialmente | Las pruebas unitarias comprueban proyección de fracciones, signos y monedas. La atribución de categorías depende del catálogo actual; ver observación. |
| Reportes de cotización y PDF | Pendiente de comprobación integral | El test que verifica doce plantillas falla antes de renderizarlas. Una comprobación aislada de los fragmentos de saldo pasó para 12/12, sin generar PDF integrales. |
| Consolidado, PDF/tickets y XLSX normal y en cola | Pendiente de comprobación integral | Los escenarios automatizados focales no prueban por sí solos todas las plantillas, filtros, archivos y recorridos HTTP. |

## Defectos confirmados

### Cotizaciones: pagos exactos rechazados

- Ubicación: `resources/js/views/tenant/quotations/form.vue:2374,2419` y `resources/js/views/tenant/quotations/form_edit.vue:1218,1261`.
- Reproducción: agregar pagos `0.10` y `0.20` a una cotización de total `0.30`. La suma de los métodos reales extraídos de los componentes es `0.30000000000000004`; la comparación con `parseFloat(total)` detecta exceso y rechaza el pago.
- Esperado: `0.10 + 0.20 = 0.30`, sin rechazo. Impacto: impide guardar una distribución válida en ambos formularios.
- Propuesta: sumar y comparar con el motor racional exacto y conservar los decimales originales; agregar regresiones para ambos formularios.

### Cotizaciones: pérdida de precio al preparar partidas

- Ubicación: `resources/js/views/tenant/quotations/form.vue:2109` y `resources/js/views/tenant/quotations/form_edit.vue:1015`.
- Reproducción: preparar una partida con precio textual `9007199254740993.12`; ambos métodos producen `9007199254740994` en `input_unit_price_value` y `item.unit_price`.
- Esperado: conservar el precio original o su fracción exacta. Impacto demostrado: pérdida de precisión en el borrador al preparar la partida. La persistencia posterior y el impacto en una cotización guardada quedan pendientes de comprobación.
- Propuesta: transportar precio y cantidad como texto o fracción, sin coerción a número binario, y verificar reapertura y guardado con servidor y base aislados.

### Tensu: total y pago sugerido imprecisos

- Ubicación: `resources/js/views/tenant/documents/invoicetensu.vue:688`.
- Reproducción: calcular una línea con total `9007199254740993.12`; el método obtiene `9007199254740996` para el total y para el pago propuesto.
- Esperado: conservar el importe exacto y adaptar sólo la representación final cuando corresponda. Impacto acreditado: cálculo visible y propuesta de pago incorrectos. No se acredita creación de factura: `submit` retorna inmediatamente en la línea 736.
- Propuesta: sustituir sumas y redondeos intermedios por `ExactAmount`, con conversión final únicamente en presentación o liquidación.

### Cobertura de PDF de cotizaciones interrumpida

- Ubicación: `tests/Unit/QuotationPdfAmountsTest.php:50`.
- Reproducción: el glob encuentra 13 rutas porque incluye `pdf/partials/quotation_narrow_items.blade.php`; la aserción espera 12 y falla antes de iterar las plantillas.
- Esperado: seleccionar las 12 plantillas principales y ejecutar las comprobaciones. Impacto: la prueba no demuestra un error monetario, pero tampoco acredita ninguna de las doce plantillas en esa ejecución.
- Propuesta: ajustar la selección para excluir parciales y añadir una verificación apropiada del parcial y de los PDF renderizados.

## Observación: categorías históricas

`modules/Report/Services/DocumentReportLines.php:35,46` agrupa mediante `relation_item.category_id`, relación con el catálogo actual. En una reproducción sintética una partida con total congelado 100 aparece en categoría 5; al cambiar sólo la categoría del producto a 6, aparece en categoría 6, manteniéndose el total en 100. Esto confirma dependencia del catálogo mutable; falta definir si la categoría del reporte debe ser histórica y comprobar la exportación HTTP completa. Si se requiere historia, conservar explícitamente la categoría al emitir; no inventar categorías de documentos antiguos.

## Evidencia del navegador

Se inspeccionó el borrador existente en `bbc.localhost/documents/create` sin guardar. Mostraba una línea de Bs. 9.140,218, ajuste de liquidación Bs. 0,002 y total a pagar Bs. 9.140,22. La pestaña cargaba `app-2f8c280a.js`, el archivo indicado en el manifiesto; sus fechas de modificación son posteriores a las fuentes inspeccionadas del formulario. Esto no demuestra equivalencia completa de todos los módulos ni verifica guardado o exportaciones. No se ejecutó compilación.

## Auditores y reproducciones

- `node scripts/audit_exact_calculations.cjs --check`: 21.531 candidatos pendientes, 2.037 errores de registro, cero errores de análisis y cero fuentes omitidas. Resultado global no satisfactorio.
- `php scripts/audit_exact_calculations.php --check`: 20.804 candidatos pendientes, 3.760 errores de registro, cero errores de análisis y cero fuentes omitidas. Resultado global no satisfactorio.
- Los candidatos son expresiones para revisar, no errores demostrados. Los resultados completos quedaron temporalmente en `/tmp/pro9-audit-web-js.json` y `/tmp/pro9-audit-web-php.json`; los conteos corresponden al árbol de trabajo examinado.
- `node /tmp/pro9-auditoria-formularios-ast.cjs`: ejecutó métodos reales extraídos de ambos componentes de cotización y de Tensu y reprodujo los tres defectos descritos. El archivo temporal puede desaparecer al reiniciar el entorno.
- `./vendor/bin/phpunit --do-not-cache-result --filter 'SaleNoteReportAmountsTest|CustomerReportAmountsTest|QuotationPdfAmountsTest|QuotationPdfAdjustmentsTest|DocumentReportLinesTest' tests/Unit`: 12 pruebas, 48 aserciones, 1 fallo por el glob de plantillas descrito.
- Reproducción sintética adicional del reporte por categoría: `DocumentReportLines::categoryAmount` pasó de categoría 5=100 y 6=0 a 5=0 y 6=100 tras cambiar la relación de categoría, sin cambiar el snapshot del total.

## Revisiones futuras propuestas

Caja, bancos y cuentas por cobrar; POS y Vende Ya; API e integraciones; compras e inventario; dashboard y contabilidad; adaptación HKA. Estas áreas requieren selección explícita antes de ampliarlas.

## Resultados adicionales de pruebas

- `node --test tests/js/exact-calculation.test.cjs tests/js/exact-note-conversion.test.cjs tests/js/fiscal-note.test.cjs tests/js/quotation-conversion-receipts.test.cjs tests/js/document-list-totals.test.cjs tests/js/local-calculations.test.cjs`: 64 pruebas aprobadas. Cubren las fuentes y simulaciones de componentes, sin acreditar el bundle del navegador.
- `./vendor/bin/phpunit --do-not-cache-result tests/Unit/ExactCalculationTest.php`: 10 pruebas y 45 aserciones aprobadas.
- `./vendor/bin/phpunit --do-not-cache-result --filter 'ExactHeaderAllocationTest|DocumentReportLinesTest|SaleNoteReportAmountsTest|CustomerReportAmountsTest|QuotationPdfAmountsTest|QuotationPdfAdjustmentsTest|FiscalLineCalculationTest' tests/Unit`: 26 pruebas, 86 aserciones y 1 fallo, correspondiente al glob de plantillas descrito.
- `php scripts/test_exact_isolated_mysql.php --do-not-cache-result --filter 'test_exact_calculations_survive_mysql_reads_grouping_discounts_and_rollback|test_free_form_full_credit_preserves_subprecision_remaining_fractions|test_free_form_debit_service_uses_only_adjustment_amount|test_sale_note_report_projects_fractional_amounts_in_pages_pdf_and_excel|test_quotation_report_uses_exact_snapshot_and_zeroes_voids_in_all_outputs|test_sales_document_workbooks_keep_exact_categories_units_and_columns|test_customer_report_keeps_notes_currencies_and_filters_in_list_and_excel' tests/Unit/FiscalEmissionSchemaTest.php`: 8 pruebas y 231 aserciones aprobadas en MySQL temporal. El contenedor fue eliminado. Estos casos no acreditan todas las variantes Web ni todos los reportes.
- `php scripts/test_exact_isolated_mysql.php --do-not-cache-result tests/Unit/ExactHttpAcceptanceTest.php`: 1 prueba integrada y 969 aserciones aprobadas en MySQL temporal. Ejercita kernel, rutas y autenticación, catálogos Web, rechazo y rollback de factura de contado insuficiente, pagos y conversión de moneda, y escenarios de conversión entre documentos. No certifica todas las variantes ni la interacción visual.

## Correcciones posteriores de la auditoría

Los tres defectos confirmados de cotizaciones y Tensu se corrigieron en las fuentes: los pagos y totales se comparan con fracciones exactas; las partidas conservan precio original y moneda al prepararse y editarse; Tensu calcula su cabecera con el motor exacto. La prueba de saldos PDF excluye el directorio de parciales y vuelve a comprobar las 12 plantillas. Las pruebas de regresión se encuentran en `tests/js/quotation-tensu-exact.test.cjs` y `tests/Unit/QuotationPdfAmountsTest.php`. Su ejecución no constituye aceptación visual de assets compilados ni acredita la creación de una factura Tensu: su envío ya estaba deshabilitado y se mantiene así.
