# Auditoría de cálculos: caja, bancos y cuentas por cobrar

Fecha: 14 de noviembre de 2026. Se examinaron caja, saldos y movimientos bancarios, transferencias, ingresos, cuentas por cobrar, cobros, devoluciones y retenciones IVA/ISLR recibidas. El árbol inicial contenía cambios previos sin confirmar. Las reproducciones utilizaron datos sintéticos y SQLite en memoria o MySQL en un contenedor temporal; no se modificaron registros reales ni lógica de producción.

## Resultado y alcance

**Se confirmaron errores de integridad y precisión; no es posible certificar la exactitud integral de estas áreas.** Los resultados aprobados corresponden a los escenarios especificados, no a todas las rutas, monedas, interfaces o reportes.

| Área | Resultado | Alcance probado |
| --- | --- | --- |
| Caja y bancos | Defectos confirmados | Controles del traslado y límites de interfaz; pruebas focales de saldos y monedas aprobadas. |
| Ingresos | Defectos confirmados | Pérdida al introducir y presentar importes grandes; redondeo de cada línea antes de sumar. |
| Cuentas por cobrar | Verificado parcialmente | Lectura racional de documentos, notas, pagos, retenciones, fondos y reembolsos; fracciones pequeñas, paginación, monedas, PDF y XLSX cubiertos por pruebas focales. El estado del crédito de caja puede quedar incorrecto con saldo positivo. |
| Cobros y devoluciones | Verificado parcialmente | Pruebas focales de cotización de pagos, vuelto, devoluciones y concurrencia; no se certifican todos los consumidores. |
| Retenciones IVA/ISLR | Defecto confirmado y cobertura parcial | Estado de crédito incorrecto para saldos positivos pequeños; algunas pruebas originales no llegan a aplicar la retención por datos de fixture incompatibles. Las copias temporales adaptadas aportan evidencia adicional, descrita abajo. |
| Reportes y exportaciones | Verificados parcialmente | Se probaron acumulaciones clasificadas, PDF/XLSX y movimientos seleccionados; falta acreditar todos los formatos, filtros y flujos de navegador. |

## Hallazgos confirmados

### Alta: un traslado puede registrar un cargo y un abono incompatibles

- **Ubicación:** `modules/Finance/Http/Controllers/BalanceController.php:235-350`, ruta `POST finances/balance/transfer`.
- **Reproducción:** mediante el controlador real en MySQL temporal, un traslado VES→VES con `amount_transform_temp='1'` y `amount_transform='1000'` guardó dos partidas `1000.000000000000` y `-1.000000000000`. El resultado neto fue **+999 VES**.
- **Esperado:** para la misma moneda, ambos lados deben conciliar exactamente. Para monedas distintas, el servidor debe validar y recalcular la equivalencia con una tasa autorizada y su política de liquidación.
- **Impacto:** una solicitud con montos incompatibles puede alterar los saldos registrados. La prueba no demuestra que la interfaz ordinaria genere esa solicitud ni implica operaciones reales.
- **Propuesta:** validar importes positivos y origen/destino, fijar la tasa y derivar en servidor el importe del destino dentro de la misma transacción; verificar límites, concurrencia, equivalencia y rollback con pruebas de ambas monedas.

### Alta: se admite una salida sin saldo de origen

- **Ubicación:** `modules/Finance/Http/Controllers/BalanceController.php:251-350`.
- **Reproducción:** una cuenta USD con saldo 0 transfirió USD 1 a una cuenta VES por Bs. 100. Se guardaron saldos de origen −1 USD y destino 100 VES. Las monedas de las dos partidas y sus snapshots quedaron correctamente clasificadas como USD y VES.
- **Esperado:** el comportamiento depende de la política autorizada de sobregiros. Si se prohíben, el servidor debe consultar el saldo exacto bajo bloqueo y rechazar la operación; si se permiten, el producto debe expresarlo y controlar límites.
- **Impacto:** actualmente no se verifica en servidor la disponibilidad, pese a que la interfaz presenta un máximo. No se concluye por sí sola la política comercial deseada.

### Alta: estado de crédito procesado con saldo positivo

- **Ubicaciones:** `app/Services/Fiscal/ReceivedRetentionService.php:205-211`, `app/Http/Controllers/Tenant/CashController.php:283` y la comparación similar en `app/Services/Fiscal/FiscalNote.php:298`.
- **Reproducción de retención:** al invocar `syncCreditBalance` con saldo exacto `0.001`, el crédito queda `PROCESSED`; con `0.005` también, y con `0.005000000001` queda `PENDING`. Se usó el método real con documento sintético y SQLite en memoria.
- **Reproducción de caja:** el controlador real, con una factura sintética de saldo exacto `0.001` en MySQL temporal, registró el crédito `PROCESSED`.
- **Esperado:** mientras el saldo exacto sea positivo, `PENDING`; al liquidarse, `PROCESSED`. `FiscalDocumentPersistence::updateSettlementState` compara con cero y puede quedar en desacuerdo con esos estados.
- **Impacto:** consumidores que dependen del estado pueden tratar el crédito como liquidado antes de tiempo. Las pruebas no demuestran pérdida del saldo numérico ni que ese valor específico provenga de una venta real.
- **Propuesta:** unificar la decisión de estado sobre saldo racional positivo y probar aplicación y reversión de IVA/ISLR, NC y cobros. La tolerancia de validación tributaria tiene un contrato distinto y debe revisarse aparte.

### Media: límites de transferencia incorrectos en interfaz

- **Ubicación:** `modules/Finance/Resources/assets/js/views/transfer_between_accounts/options.vue:181-200`.
- **Reproducción:** seleccionar un banco con saldo `0.00` conserva el máximo previo `99999`, porque sólo se asigna cuando el máximo es distinto de cero. Un saldo `9007199254740993.12` se convierte con `parseFloat` en `9007199254740994`.
- **Esperado:** usar el saldo actual, incluso si es cero, y conservar su precisión sin conversión a número binario. El límite del navegador no sustituye la validación transaccional del servidor.

### Media: ingresos pierden precisión al capturar y sumar

- **Ubicaciones:** `modules/Finance/Resources/assets/js/views/income/partials/item.vue:95` y `modules/Finance/Resources/assets/js/views/income/form.vue:341-364`.
- **Reproducción 1:** una entrada textual `9007199254740993.12` se convierte a `9007199254740994` como `total_original`; este valor alimenta el cálculo posterior.
- **Reproducción 2:** dos líneas VES de `0.004`, convertidas a USD a tasa 1, se redondean individualmente a `0.00` y suman `0.00`; la suma exacta es `0.008` y su liquidación final a céntimos es `0.01`.
- **Esperado:** conservar texto/fracción original, convertir y sumar exactamente, y aplicar el redondeo una sola vez en la frontera de liquidación. Definir expresamente la política de conversión por línea si el documento requiere una liquidación distinta.
- **Propuesta:** sustituir `parseFloat` y proyecciones intermedias por `ExactAmount` y snapshots; verificar guardado y reapertura reales con tasas y cantidades representativas.

### Baja: formato de detalle de ingresos altera importes grandes

- **Ubicación:** `modules/Finance/Resources/assets/js/views/income/partials/detail-drawer.vue:413-429`.
- **Reproducción:** `formatMoney('9007199254740993.12')` presenta `Bs. 9.007.199.254.740.994,00`, en lugar de `Bs. 9.007.199.254.740.993,12`.
- **Impacto:** representación incorrecta; no se demostró que este formateador modifique el importe guardado. Propuesta: formatear desde texto o fracción exacta.

## Riesgo pendiente de confirmar

`app/Services/Fiscal/IslrRetentionService.php:62-73` comprueba positividad después de representar a 12 decimales. Una fracción positiva `1/100000000000000` se representa como cero. No se demostró que exista un comprobante admisible con esa base ni se ejecutó el flujo HTTP completo; revisar junto con las reglas de precisión y liquidación antes de clasificarlo como defecto de comprobante.

## Evidencia y pruebas

- Reproducciones de interfaz: `node informes/evidencias/auditoria_caja_2026_11_14/formularios.cjs`; ejecuta los métodos Vue extraídos y reproduce límite, ingreso, formato y redondeo intermedio.
- Reproducciones MySQL de traslados y crédito de caja: `php scripts/test_exact_isolated_mysql.php --do-not-cache-result --filter 'test_same_currency_transfer_accepts_inconsistent_legs|test_zero_balance_cross_currency_transfer|test_cash_credit_marks_small_positive_balance_processed' informes/evidencias/auditoria_caja_2026_11_14/TransferIntegrityTest.php`: 3 pruebas, 6 aserciones. Las primeras dos se ejecutaron también juntas y la tercera por separado. Hubo fallos iniciales de fixture/aserción antes de corregir las reproducciones temporales.
- Reproducción del estado por retención: `APP_ENV=testing CACHE_DRIVER=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php informes/evidencias/auditoria_caja_2026_11_14/retencion_estado.php`. Saldos 0, 0.001 y 0.005 producen `PROCESSED`; 0.005000000001 produce `PENDING`. El doble sintético no acredita el ciclo completo del comprobante.
- `./vendor/bin/phpunit --do-not-cache-result tests/Unit/ExactReceivablesTest.php`: 13 pruebas, 275 aserciones aprobadas. `./vendor/bin/phpunit --do-not-cache-result tests/Unit/ExactOutstandingSummaryTest.php`: 1 prueba, 6 aserciones aprobadas.
- `./vendor/bin/phpunit --do-not-cache-result --filter 'MovementBalancesTest|TransferAccountAmountTest|ClassifiedCashWorkbookTest|ExactFinanceCalculationTest' tests/Unit`: 13 pruebas, 127 aserciones aprobadas.
- `DB_HOST=127.0.0.1 RES_OPTIONS='attempts:1 timeout:1' ./vendor/bin/phpunit --do-not-cache-result --filter 'PaymentQuoteTest|HkaIslrRetentionTest' tests/Unit`: 35 pruebas, 263 aserciones aprobadas; transporte simulado.
- `node --test tests/js/movement-summary.test.cjs tests/js/transfer-cash-currency.test.cjs tests/js/cash-opening-errors.test.cjs tests/js/cash-report-filters.test.cjs`: 11 pruebas aprobadas.
- Con el filtro `test_classified_cash_mysql_open_close_and_reports_share_the_same_native_balances|test_transfers_capture_the_currency_of_each_destination_and_reject_stale_cash_currency|test_free_form_paid_invoice_credit_balance_and_refund|test_note_retention_reversal_detects_subprecision_remaining_base|test_full_credit_split_mobile_and_cash_refunds_enforce_the_accumulated_limit|test_simultaneous_document_receipts_after_prior_read_respect_balance|test_cash_opening_keeps_its_own_currency_when_catalog_methods_change`, ejecutado con `php scripts/test_exact_isolated_mysql.php --do-not-cache-result --filter ... tests/Unit/FiscalEmissionSchemaTest.php`: 7 pruebas, 342 aserciones aprobadas. Log temporal `/tmp/pro9-finanzas-mysql.log`.
- Retenciones y cobro entre monedas, pruebas originales en MySQL temporal: `test_note_retention_reversal_detects_subprecision_remaining_base` y `test_cross_currency_credit_receipt_and_iva_retention_keep_balance_and_snapshot` en sus dos ambientes: 3 pruebas, 99 aserciones aprobadas. Log `/tmp/pro9-retenciones-passing.log`.
- `node --test tests/js/payment-classification.test.cjs tests/js/fiscal-payment-ui.test.cjs tests/js/change-disbursements.test.cjs tests/js/note-refund-rate.test.cjs tests/js/iva-retention-ui.test.cjs tests/js/received-retention-list.test.cjs tests/js/movement-summary.test.cjs tests/js/transfer-cash-currency.test.cjs tests/js/exact-outstanding-ui.test.cjs`: 95 pruebas, 94 aprobadas y una fallida por `this.enteredReceiptAmount is not a function` en el contexto simulado de `tests/js/iva-retention-ui.test.cjs:12`; no demuestra por sí solo un error monetario. Salida `/tmp/pro9-finanzas-js.log`.
- Grupo PHP focal en MySQL temporal: 65 pruebas, 703 aserciones, 1 error en `HkaIvaRetentionPayloadTest` por conexión `tenant` no configurada. Salida `/tmp/pro9-finanzas-php.log`.
- Grupo original adicional IVA/ISLR: 7 pruebas, 99 aserciones y 4 errores antes de aplicar retenciones; los fixtures declaran intermediación incompatible con la naturaleza del método. Salida `/tmp/pro9-retenciones-mysql.log`. No se consideran aprobadas las cuatro pruebas.
- Los auditores globales `node scripts/audit_exact_calculations.cjs --check` y `php scripts/audit_exact_calculations.php --check` terminaron con código 1: JavaScript, 21.462 candidatos y 2.031 errores de registro; PHP, 20.804 candidatos y 3.760 errores de registro. Ambos informaron cero errores de análisis y fuentes omitidas. Los candidatos no son defectos confirmados; estas cifras incluyen todo el proyecto, no sólo esta auditoría.

No se ejecutó compilación ni se acreditó el funcionamiento del paquete de navegador. La aceptación HKA real y las aplicaciones externas quedan fuera del alcance de esta verificación.
- Se repitieron las cuatro pruebas originales de retenciones fallidas con copias temporales de sus traits, cambiando únicamente la intermediación a `none` para efectivo: 3 de 4 pasaron, 395 aserciones y 1 error porque el escenario USD sujeto a IGTF no lo tenía habilitado. La repetición del caso IVA con IGTF 3 % avanzó hasta una aserción que esperaba pagos por 208 y obtuvo 214,24; la diferencia numérica de 6,24 coincide con el IGTF. El código crea una aplicación derivada con `receipt_parent_id` en el documento para el cobro inicial. La aserción original suma ambas; el caso completo queda pendiente hasta comprobar por separado principal e impuesto. Los tests originales siguen fallidos y sin editar. Copias y logs: `/tmp/pro9-retenciones-fixture-AQlkmC/`.
- La repetición posterior del caso IVA con una copia temporal adaptada pasó: **1 prueba y 253 aserciones**, con aplicación, reversión, idempotencia y reemplazo en VES/USD. La copia cambió la intermediación para efectivo, habilitó IGTF 3 %, distinguió principal de aplicación derivada mediante `receipt_parent_id` y verificó el subtotal 232 y total 238,24 en USD conforme a `ExactDocumentSummary`. Log `/tmp/pro9-retenciones-fixture-AQlkmC/result-igtf-total.log`. Esta adaptación no convierte los tests originales en aprobados y el transporte HKA fue simulado.

## Reproducciones conservadas

Las fuentes sanitizadas se copiaron a `informes/evidencias/auditoria_caja_2026_11_14/`: `formularios.cjs`, `retencion_estado.php` y `TransferIntegrityTest.php`. Se ejecutan desde la raíz del proyecto, este último exclusivamente mediante `scripts/test_exact_isolated_mysql.php`. **Sus aserciones documentan el comportamiento incorrecto observado**, por lo que deben invertirse cuando se corrija; que pasen no acredita que el sistema sea correcto.
