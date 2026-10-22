# Facturas a crédito sin abono — HKA DEMO, 21/10/2026

## Contrato y alcance

Comprobantes web, factura01/0101, transacción01, Medios digitales DEMO y VES/USD. Sólo facturas nuevas sin cobros vigentes, anticipos, retenciones/previsiones, fondos, terceros ni regímenes. No habilita crédito con abono, exportaciones, otros canales ni producción.

`HkaCreditInvoice` agrega `forma=99`, descripción «Crédito comercial pendiente de cobro», por el total principal final HKA, después de adaptar moneda y antes de validar el esquema. Conserva `tipoDePago=Crédito`, vencimiento, moneda local, tasa de ocho decimales, impuestos e inventario. USD usa principales VES y equivalentes USD mediante la adaptación existente. No genera recibos, efectivo ni IGTF; el saldo sigue íntegro.

## Compatibilidad y ciclo de vida

El guardado web autorizado captura `response.credit_invoice_contract=no_advance_v1` al crear una factura elegible, después de pagos y aplicaciones comerciales. No acepta la marca del navegador. Preparar copia explícitamente esa marca al snapshot interno y nunca la transmite a HKA. Consultar, editar o reenviar documentos anteriores no los incorpora al contrato. La edición conserva únicamente la marca ya existente al invalidar UUID/payload y exige envío explícito.

`hka_credit_invoices.demo_no_advance_verified` controla la aceptación verificada. Desactivado conserva la venta y bloquea preparación/envío con diagnóstico en servidor y DTO. Antes de preparar se vuelve a comprobar elegibilidad: un abono posterior bloquea la declaración del total. Operaciones preparadas conservan su payload. Confirmación, consultas, permisos, saneamiento y concurrencia usan el flujo común; cobrar posteriormente reduce saldo sin reconstruir el payload confirmado. No hay rutas, tablas, migraciones ni estados nuevos. La factura00000723 permanece intacta.

## Aceptación real

[Evidencia saneada](evidencias/facturas_credito_sin_abono_hka_demo_2026_10_21_223736_159293.json). Las cuatro facturas fueron confirmadas por emisión y consulta, con A4/A5 descargados:

| Moneda | Líneas | Número sintético | Control | Total y saldo | Cobros |
|---|---|---|---|---|---|
| VES | Gravadas16% | 1188139386 | 00-00000726 | 232 VES | 0 |
| VES | Gravadas16% y exentas | 1188139387 | 00-00000727 | 282 VES | 0 |
| USD | Gravadas16% | 1188139388 | 00-00000728 | 232 USD | 0 |
| USD | Gravadas16% y exentas | 1188139389 | 00-00000729 | 282 USD | 0 |

Se usó una base temporal con datos sintéticos y credenciales DEMO cifradas mediante clave efímera. El proceso no envía correo ni anula facturas. Los comprobantes remotos de prueba permanecen en DEMO. La huella de documentos, emisiones, correlativos y pagos del tenant origen coincide antes/después; la base temporal fue eliminada.

Los originales muestran «Condición de Pago: Crédito», vencimiento y descripción de crédito comercial dentro de «Formas de Pago». Esta sección del proveedor no representa un cobro en Pro9. La revisión visual detecta en el A4 USD mixto un recorte de la tasa junto al texto legal inferior; el texto del PDF sí contiene la tasa completa. Se conserva el original HKA, sin reescribirlo. No se acredita homologación productiva.

## Pruebas y repetición

HkaCreditInvoiceTest cubre importes finales VES/USD, vencimiento, tasa, inmutabilidad, exclusiones y fracciones exactas. Los casos MySQL `test_credit_invoice_*` cubren saldo íntegro, cobro posterior, marca web frente a dato publicado, factura723 rechazada, edición, rollback sin HTTP, timeout/consulta, rechazos1015/1016, identidad contradictoria, respuesta incompleta, emisión concurrente única y descuentos/cargos. Se ejecutan también regresiones de constructor, interpretación/transporte, autorización/edición, commit exterior y envío automático, más interfaz JS para acción directa, bloqueo y saldo conservado. No se modificaron fuentes frontend ni se ejecutó compilación.

El auditor global conserva pendientes amplios (PHP17579 y JS19556), sin errores de análisis. Las comparaciones nuevas de códigos/estados y metadatos tienen revisión individual en el registro de precisión; HkaCreditInvoice no conserva candidatos pendientes. El dinero usa Decimal/BigRational; la declaración reutiliza el total fiscal final. La elegibilidad del listado precarga relaciones en lote y no consulta por fila; el snapshot sólo lee sus entradas concretas, sin evaluar la política de edición.

Resultado:41 pruebas unitarias/217 aserciones en la regresión HKA y autorización/edición,9 casos MySQL/448 aserciones en ejecuciones acotadas y52 pruebas JS. Los23 límites técnicos nuevos tienen revisión individual; el auditor global no acredita un cierre del resto del repositorio. Capacidad habilitada tras aceptación real y pruebas locales satisfactorias.

`PRO9_FISCAL_MYSQL_TESTS=1 php -d memory_limit=1G scripts/verify_hka_credit_invoices_demo.php bbc.localhost` crea cuatro comprobantes remotos nuevos y requiere autorización expresa de emisión DEMO. Comprueba ausencia del número antes de cada envío; una consulta ambigua bloquea el caso. El reporte conserva sólo estados, códigos, importes, controles y hashes, sin JWT, credenciales ni respuestas privadas.
