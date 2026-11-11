# Pruebas de factura web con IGTF

Fecha: 8 de noviembre de 2026. Alcance: `/documents/create` de BBC, Forma libre/DEMO, emisor SPE vigente, IGTF habilitado al 3 %. Se usaron cliente y productos de prueba, caja existente y el método 06 Efectivo Dólares IGTF, activo y asociado al 3 %. La factura exploratoria 00000005 se cobró con el método 10 antes de seleccionar el 06 para los casos posteriores. Antes de las pruebas, el producto ficticio MOCK-ITEM-VES-001 tenía stock global 20 y no tenía fila de almacén; se le asignó ese mismo saldo 20 en el almacén 1, sin aumentar el stock global. Las facturas y movimientos permanecen registrados; no se enviaron a HKA ni por correo.

| Caso | Comprobante | Pantalla y persistencia verificadas |
|---|---|---|
| Dos productos físicos con cantidades decimales | 00000015 | 1,5 unidades por Bs. 750 y 1,25 por Bs. 125; principal Bs. 875, IVA Bs. 120,689655172414, IGTF USD 0,26, ingreso USD 9,01 y saldo cero. Stock de almacén posterior: 967,5 y 18,75. |
| USD 100 y cobro USD | 00000014 | USD 100 principal, USD 3 IGTF, ingreso USD 103 y saldo cero; 20 unidades del producto de prueba. |
| Factura USD y cobro USD | 00000006 | USD 0,56 principal, USD 0,02 IGTF, ingreso USD 0,58 y saldo cero. Método 06 conserva IGTF 3 % en snapshot. |
| Factura VES y cobro USD, tasa 100 | 00000007 | Bs. 500 de principal, USD 0,15 IGTF, ingreso USD 5,15 y saldo cero. |
| Mixto VES/USD, tasa 100 | 00000008 | Bs. 250 sin IGTF y USD 2,50 más USD 0,08; dos ingresos reales, total Bs. 508 y saldo cero. |
| Crédito con abono USD, tasa 100 | 00000009 | USD 2 de principal más USD 0,06, ingreso USD 2,06, saldo y cuota Bs. 300. |
| Efectivo con vuelto, tasa 100 | 00000010 | Principal entregado USD 6, vuelto USD 1, principal neto USD 5, IGTF USD 0,15 e ingreso USD 5,15. |
| Control VES | 00000011 | Bs. 500 cobrados, IGTF cero y saldo cero. |
| Ajuste cambiario con tasa 897,5383 | 00000013 | Bs. 500 aplicados, USD 0,56 principal más USD 0,02 IGTF, ingreso USD 0,58, saldo cero y ajuste exacto en el snapshot; sin vuelto. |
| Control simulado con intermediación | 00000017 | USD 5, IGTF cero, ingreso USD 5, saldo cero y clasificación `financial_institution`. Se simuló la opción de intermediación con el método efectivo 06; no acredita una operación bancaria real. |
| Rechazo de destino, intermediación y tasa y reintento | 00000016 | El formulario mantuvo el borrador ante los tres rechazos; se corrigió la tasa y se guardó con USD 0,56 más USD 0,02, un cobro real, ingreso USD 0,58 y saldo cero. |
| Rechazo de vuelto fraccionado y reintento | 00000012 | USD 0,56 a tasa 897,5383 fue rechazado; el formulario conservó el borrador y mostró el motivo. Con importe USD 5 y tasa de cobro 100 se guardó una factura, con un cobro real e IGTF USD 0,15. |

La tasa ausente muestra ahora «Indique una tasa VES por USD válida y mayor que cero» y bloquea el envío; las validaciones de destino e intermediación también conservaron el borrador. La verificación visual confirmó el mensaje real del servidor al rechazar un vuelto no expresable en centavos. Tras corregir el error de redondeo, el caso de USD 0,56 a la tasa real se guardó como 00000013. La propuesta, la vista previa y la persistencia tratan el residuo menor a medio centavo como ajuste sin fabricar vuelto. Se verificó en pantalla el texto «Ajuste por liquidación en centavos: -2.62 VES», con principal Bs. 500 e IGTF USD 0,02. Una diferencia que represente un vuelto no nulo no expresable en centavos continúa rechazándose.

Las 104 pruebas JavaScript focalizadas pasaron, al igual que 41 pruebas PHP con 171 aserciones. En MySQL temporal pasaron dos pruebas de persistencia y cotización con 33 aserciones y dos pruebas de vuelto y reversión transaccional con 40 aserciones. El watcher incorporó los cambios de interfaz. Para las facturas 00000006 a 00000017 se consultó el estado de emisión `not_requested`. Las pruebas son locales y no acreditan emisión remota.

Límites del primer recorrido: el control de intermediación de 00000017 es sintético y usa el método de efectivo, ya que BBC no tiene una cuenta bancaria USD activa; no demuestra una transacción bancaria. La prueba aislada `test_spe_invoice_reuses_a_sale_note_receipt_without_new_igtf` falló en su fixture de inventario de nota de venta; es otro flujo y no acredita un fallo del formulario de factura nuevo. Las pruebas de rechazo preservaron el borrador y el reintento 00000016 produjo un solo cobro; no se registró una captura integral de todas las tablas antes de cada rechazo.

## Cierre por redondeo USD, incluidos pagos mixtos — 8 de noviembre de 2026

Se corrigió la diferencia pendiente que aparecía al proponer USD 5,57 para una venta de Bs. 5.000 a tasa 897,53830000. La regla común compara el importe recibido con el remanente convertido y liquidado HALF_UP a centavos. La última fila nueva de efectivo USD aplica el remanente completo sólo si coincide con esos centavos. El ajuste positivo o negativo se conserva como fracción en `payment_adjustment`, se muestra antes de confirmar y se imprime en el detalle del comprobante local. Los importes recibidos y el IGTF no se reconstruyen desde el principal aplicado.

La cotización, vista previa y persistencia comparten el cierre. El servidor calcula el ajuste sobre el lote normalizado y lo pasa mediante contexto interno; no acepta un cierre indicado por el cliente. La respuesta añade `settlement_adjustment_exact`. El formulario muestra el monto aplicado de una cotización vigente sin reemplazar los operandos del borrador ni modificar los dólares recibidos.

| Caso desde `/documents/create` | Factura | Esperado | Obtenido en pantalla y registros |
|---|---|---|---|
| Efectivo USD | 00000019 | Bs. 5.000 aplicados; USD 5,57 + 0,17 IGTF; ajuste Bs. +0,711669; saldo cero | Coincide. Un ingreso USD 5,74, movimiento 25 y una salida de inventario de una unidad. |
| VES primero, USD después | 00000020 | Bs. 1.000 + USD 4,46; USD 0,13 IGTF; ajuste Bs. −3,020818; saldo cero | Coincide. Movimientos 26 y 27 por Bs. 1.000 y USD 4,59; una salida de inventario de una unidad. |
| USD primero, VES después | 00000021 | Mismos importes y ajuste que el caso mixto anterior | Coincide. Movimientos 28 y 29 por USD 4,59 y Bs. 1.000; una salida de inventario de una unidad. |

El principal documental es Bs. 5.000 en las tres facturas. El total exacto de 00000019 es Bs. 5.152,581511 y su total liquidable Bs. 5.152,58; en 00000020/21 son Bs. 5.116,679979 y Bs. 5.116,68. Esas representaciones finales conservan su ajuste documental separado del ajuste de conversión del cobro. Los tres saldos racionales son `0/1`, las facturas están pagadas y sus emisiones siguen en `not_requested`.

Antes de este recorrido había 18 documentos, 31 filas de pago y 23 movimientos. Después hay 21 documentos, 39 filas de pago y 28 movimientos: cinco recepciones reales y tres aplicaciones internas de IGTF, sin otra recepción por el impuesto. El stock global y del almacén 1 del producto 00004 pasó de 965,5 a 962,5; cada factura tiene una salida Kardex de una unidad. Se conservó Forma libre/DEMO y el correo automático desactivado. Las ventas de prueba permanecen registradas.

Verificación automatizada de esta corrección:

- PHP: 38 pruebas y 169 aserciones de cotización, vista previa y moneda del cobro.
- JavaScript: 112 pruebas focalizadas, incluidos presentación del ajuste exacto, cotizaciones obsoletas y preservación de los operandos USD.
- MySQL temporal: 8 pruebas y 190 aserciones de persistencia, pagos mixtos en ambos órdenes, reintento idempotente, vuelto y rollback. Un cobro USD 5,56 no cierra Bs. 5.000 al contado; al usar crédito conserva Bs. 9,69 de saldo y cuota. También se cubrieron varias filas USD, cobros anteriores, abonos posteriores y el límite exacto de medio centavo en las pruebas PHP.
- Se verificaron los assets actualizados por el watcher existente; no se inició otra compilación. `git diff --check` y las comprobaciones de sintaxis PHP pasan.
- Se ejecutaron las auditorías globales de cálculos exactos PHP y JavaScript: no tuvieron errores de análisis ni fuentes omitidas, pero su comprobación global conserva candidatos y revisiones pendientes del repositorio. Se registraron individualmente 25 expresiones de esta corrección con su categoría y evidencia; esos resultados no acreditan el cierre de la auditoría global.

Evidencias: [registros conciliados](evidencias/igtf_redondeo_2026_11_08.json), [confirmación de la factura 00000019](evidencias/igtf_redondeo_factura_00000019.png), [propuesta del caso principal](evidencias/igtf_redondeo_principal.png) y [propuesta del pago mixto](evidencias/igtf_redondeo_mixto.png). Las capturas de propuesta conservan el diálogo de agregar producto abierto; la confirmación y los registros identifican las ventas guardadas.
