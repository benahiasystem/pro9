# Cálculos locales de comprobantes

Implementación del 14/11/2026 para Facturación, POS, POS rápido y Venta rápida (`/pos/garage`), tipos 01 y 80. No cambia el esquema ni documentos históricos.

## Contrato

- Las partidas y cabeceras conservan los motores exactos existentes. `local-payment-quote.js` calcula cobros, IGTF, conversiones, saldo y vuelto con `ExactAmount` y fracciones. Facturas aplican las reglas de `PaymentQuote`/`ReceiptChange`; notas de venta aplican `TenderAllocation`, descontando primero los abonos históricos exactos.
- El total entregado incluye IGTF. El cálculo no escribe el neto aplicado sobre ese total ni cambia el reparto elegido para el vuelto. Los históricos conservan sus fracciones y no financian nuevos vueltos.
- Cada consumidor activa `localCalculations` expresamente. Ediciones de facturas y los demás consumidores del mixin conservan la estrategia anterior.
- Crédito excluye los cobros nuevos conservados en el borrador. Volver a contado los recupera. Las cuotas se distribuyen sobre el saldo y conservan fechas y pesos editados.
- La cabecera exacta con IGTF y cada pago del impuesto se liquidan separadamente, como en persistencia. Se muestran también los faltantes de céntimos producidos por esas conversiones; no se sustituyen por un saldo cero.

`POST /documents/calculation-context` entrega versión, revisión, IGTF, perfiles por fecha, métodos y destinos vigentes. La memoria del formulario se identifica por origen, empresa, cliente, sucursal y fechas. Cambiar metadatos o catálogos actualiza el contexto; editar importes, cantidades o tasas manuales recalcula sin cotización HTTP. Las respuestas anteriores no restauran configuración obsoleta. La configuración local pertenece a cada componente, sin modificar el singleton utilizado por otros módulos.

Cantidades y precios de líneas existentes en POS se calculan antes de esperar inventario. La verificación de existencias sigue siendo obligatoria en segundo plano y al guardar. Mientras está pendiente o falla, el botón de generar queda bloqueado. Las comprobaciones conservan una identidad por línea al reconstruirla por moneda; una respuesta vieja o una línea eliminada no puede modificar el borrador actual. Los errores de red permiten reintentar. La primera selección de un producto conserva la comprobación de inventario existente.

## Confirmación del servidor

Los clientes nuevos envían `expected_calculation`, versión 1 y revisión del borrador. Incluye identificadores de partidas/afectaciones, importes exactos y liquidables, cobros y fracciones aplicadas, métodos, cuenta o turno de caja, desembolsos, cuotas, saldo y ajuste. Los identificadores se comparan como cadenas, no como importes.

El servidor conserva la autoridad: vuelve a calcular, valida y compara dentro de la transacción. Se bloquea la empresa antes de establecer la lectura consistente. La comparación ocurre después de pagos, liquidaciones y cuotas, antes de adjuntos de pagos, PDF, despacho y emisión. Una diferencia revierte los registros y devuelve HTTP 409:

```json
{
  "success": false,
  "code": "calculation_changed",
  "message": "Los cálculos se actualizaron. Revise los valores y confirme nuevamente.",
  "calculation": {"version": 1, "revision": "revision-del-borrador"},
  "differences": [{"field": "balance", "expected": "0.00", "actual": "0.01"}]
}
```

`calculation` contiene el resumen completo; el ejemplo omite sus campos monetarios. La pantalla presenta la corrección y conserva entradas y selecciones. Sólo otro clic confirma ese mismo borrador; una edición real descarta la aprobación. Las conversiones internas de presentación no la invalidan. No se acepta una respuesta para un borrador reemplazado. Los clientes sin resumen esperado mantienen su contrato previo.

## Evidencia automatizada

Comandos ejecutados, sin compilación manual:

```sh
node --test tests/js/local-calculations.test.cjs tests/js/pos-hka-email.test.cjs tests/js/igtf-settings-ui.test.cjs tests/js/invoice-tax-summary.test.cjs tests/js/default-receipt-methods.test.cjs tests/js/pos-exchange-rate.test.cjs tests/js/pos-quantity-currency.test.cjs tests/js/fiscal-payment-ui.test.cjs
RES_OPTIONS='attempts:1 timeout:1' ./vendor/bin/phpunit --filter 'PaymentQuoteTest|LocalPaymentQuoteParityTest|CalculationExpectationTest' tests/Unit
DB_HOST=127.0.0.1 RES_OPTIONS='attempts:1 timeout:1' PRO9_FISCAL_MYSQL_TESTS=1 ./vendor/bin/phpunit --filter 'test_local_calculation' tests/Unit/FiscalEmissionSchemaTest.php
```

- JavaScript: 247 pruebas aprobadas.
- PHP unitario: 33 pruebas, 286 aserciones aprobadas. Los casos compartidos PHP/JavaScript incluyen USD 60/70 (vuelto 8,20), reparto de 40, monedas y tasas mixtas, neto cero, métodos bancarios, histórico, tasa fraccionaria y montos superiores al entero seguro de JavaScript.
- MySQL temporal: dos pruebas de discrepancia/reconfirmación en factura y nota, configuración modificada desde otra conexión, cambio de turno y cabecera fraccionaria; 37 aserciones aprobadas. Verifica ausencia de registros, movimientos, inventario y archivos parciales antes de confirmar. El cálculo de JavaScript de partidas/cabecera se compara además con la persistencia real.
- Se ejecutó también la regresión de factura con un pago devuelto completamente y destinos mixtos seleccionados, aprobada junto con los casos transaccionales (tres pruebas, 46 aserciones en esa ejecución anterior).
- Se prueban contextos tardíos, doble confirmación, cambios de borrador, crédito/cuotas/fechas, cambio factura/nota, fallo y reintento de inventario, respuesta invertida, fila eliminada y precio fraccionario durante una comprobación pendiente.

## Medición

Las últimas 20 cotizaciones anteriores registradas por Debugbar el 14/11 tuvieron mediana de 454,69 ms de ejecución del request (356,11–599,40 ms). Un ensayo de 500 cálculos locales del caso USD 60/70 con cabecera exacta dio mediana de 0,89 ms y percentil 95 de 1,33 ms. Son medidas distintas: petición Laravel completa frente al motor JavaScript en Node; no representan una medición de extremo a extremo del navegador.

En el navegador se observó que el IVA, total y vuelto de Venta rápida ya estaban actualizados mientras seguía visible «Verificando disponibilidad de productos…», con el botón de pago bloqueado. La lentitud y respuestas invertidas se simulan además mediante promesas de red sin resolver en las pruebas Vue. La generación sigue requiriendo conexión.

## Verificación visual final

El watcher del usuario produjo `app-25412876.js`, identificado tanto en el manifiesto como en Facturación, POS y Venta rápida después de recargar para incorporar las fuentes finales. Las ediciones se hicieron después sin recargar componentes.

- Facturación: producto USD 60, efectivo USD 70, total USD 61,80 y vuelto USD 8,20; Caja General seleccionada. Se revisaron también el resumen, su separación de cabeceras y el total a 390 px.
- POS: total Bs. 5.100, Pago Móvil Bs. 100 y efectivo Bs. 6.000; se aplica Bs. 5.100 y se muestra un vuelto único de Bs. 1.000. Crédito oculta los pagos nuevos; al volver a contado se recuperan los importes y métodos del borrador.
- Venta rápida: cantidad y precio actualizan IVA, saldo y vuelto antes de terminar inventario. USD 70 a tasa 897,5383 para Bs. 5.000 produjo vuelto USD 64,26; cambiar manualmente la tasa a 1.000 produjo USD 64,85. Cambiar factura/nota conserva el último dinero ingresado.
- El acceso de Nginx desde las 20:47 UTC registró **cero** POST a `documents/payment-quote` y **cero** a `fiscal-profiles/evaluate` durante estas ediciones. Las llamadas de contexto corresponden a apertura y cambios de metadatos.

En BBC se verificaron borradores. Las creaciones y sus efectos transaccionales se ejecutaron en las bases MySQL descartables de pruebas. Las capturas finales están en `/tmp/pro9-calculo-local-escritorio.jpg` y `/tmp/pro9-calculo-local-movil.jpg`.
