# Facturas de exportación de bienes HKA

Implementación iniciada el 9 de octubre de 2026 y validación de interfaz retomada el 11 de octubre, para instalaciones nuevas. La exportación se registra desde Comprobantes web como Factura `01`; HKA también recibe `01`. Producción, API, POS, servicios, notas de exportación, regímenes especiales, terceros y la operación genérica `0200` permanecen bloqueados.

## Contrato comercial

| Operación local | INCOTERM / `tipoDeVenta` HKA |
|---|---|
| 0201 | FOB |
| 0202 | CIF |
| 0203 | EXW |

`ExportInvoicePolicy` valida el alcance antes del guardado. La capacidad web la establece el controlador, nunca un campo del navegador. Medios digitales y DEMO son obligatorios. Todos los bienes usan `EXP` (Exportación — IVA 0 %), activo y no gratuito; una factura interna o un módulo distinto no admite esa afectación. Las compras, ítems y ventas internas siguen usando 10/20. La petición de alta/edición de ítems y el catálogo de validación previa del Excel también rechazan EXP como afectación del producto: se selecciona al facturar la exportación. Los códigos retirados no se remapean.

El comprador requiere identidad local `ND`, país distinto de VE, dirección y tipo real externo (`NIT`, `RUT`, `EIN`, etc.: 2–4 letras mayúsculas, distinto de ND). El identificador conserva literalmente 1–45 caracteres ASCII alfanuméricos, punto y guion, empezando por alfanumérico. No se añaden prefijos ni se envía ND a HKA. La unicidad incluye tipo de persona, país, tipo externo y número; el DDL agrega un índice compuesto para impedir duplicados concurrentes. ND no se admite para proveedores. Sus direcciones conservan países extranjeros y eliminan territorio/nacionalidad venezolanos. La dirección seleccionada debe pertenecer al cliente.

## Cálculos y snapshots

El servidor recalcula líneas y totales; no confía en los importes enviados por el formulario. `total_exportation` conserva el valor neto de exportación con descuentos/cargos y se mantiene separado de `total_exonerated`, `total_taxed` y `total_igv`, que son cero. `document_currency_totals.exportation` conserva su equivalencia VES con la tasa de hasta ocho decimales. La tasa cero no cambia la tasa general doméstica de Localization.

HKA carece de un código de impuesto específico para exportación en el catálogo 10 consultado: el adaptador usa `codigoImpuesto=E`, alícuota cero y `montoExentoTotal` con el valor exportado, junto al INCOTERM. Ese nombre externo no reclasifica localmente la factura como Exento. Para documentos USD, HKA exige los importes principales y las líneas en VES, y `totalesOtraMoneda.moneda=USD`: valida la división por la tasa VES/USD. El adaptador transforma una copia y conserva intacta la moneda documental USD, los cobros y los snapshots originales de Pro9. VES no incluye ese nodo.

Los descuentos y cargos globales se distribuyen proporcionalmente en las líneas de la copia HKA, con asignación acumulada de centavos y sin repetir descuentos ya distribuidos. Se declara `precioAntesDescuento` cuando corresponde. Los residuos de conversión se concilian contra el total VES congelado. HKA recibe ajustes de línea y totales netos; Pro9 conserva sus ajustes globales y su valor exportado neto. Esta adaptación responde a rechazos reales DEMO de las validaciones 1009/1010/1012/1062/1064/1068; no se alteran payloads de ventas internas.

El snapshot del comprador incluye tipo externo y país/dirección seleccionados. `document_fiscal_data.catalog_snapshot.operation_type` conserva operación e INCOTERM. El constructor HKA exige correspondencia entre esos datos, las líneas y los totales; no consulta catálogos ni personas al preparar un payload.

Se reutilizan la numeración local, el callback después del commit, el UUID preparado, bloqueo/conciliación, control devuelto por HKA, almacenamiento A4 original, conversión A5, ticket 80MM con QR y correo HKA. Rollback comercial no transmite. Ninguna prueba real de esta etapa solicita correo (`notificar=No`, envío automático desactivado).

Una edición previa al registro remoto conserva identidad documental, moneda y cobros conforme a DocumentEditPolicy. Puede cambiar el INCOTERM, pero no convertir venta interna en exportación o viceversa. Una operación preparada se invalida con nuevo UUID y exige Enviar HKA explícito. Se vuelve a cargar la relación Invoice para conservar el nuevo INCOTERM en el snapshot. Las notas de crédito/débito sobre exportaciones se rechazan; un cobro posterior que requiera una nota IGTF de exportación también se bloquea con un motivo explícito y sin registrar el pago. El IGTF de pagos iniciales conserva el flujo fiscal existente.

## Datos iniciales y despliegue

Se modifica únicamente el consolidado: `persons.external_identity_type`, índice de identidad extranjera y `document_currency_totals.exportation`; ND/EXP/0201/0202/0203 nacen activos. 0200 sigue inactivo y no puede activarse. FE se conserva como referencia de catálogo: el flujo emitido sigue siendo Factura 01. No se ejecutan migraciones ni conversiones sobre tenants existentes.

El formulario ofrece INCOTERM, compradores extranjeros y precio sin IVA; las líneas no admiten servicios. El selector utiliza el catálogo cargado de operaciones y la búsqueda por ID recibe el contexto de operación mediante la petición inyectada por Laravel. El catálogo de identidades declara clave de texto para conservar ND y los demás IDs al cargar relaciones. El resumen, detalle y ticket distinguen exportación e IVA cero.

La edición mantiene el comprador al cambiar entre INCOTERM, conserva la tasa de ocho decimales y la fecha de vencimiento, y no sustituye esos datos por una consulta BCV. Los cargos globales se restauran desde las claves JSON devueltas por el servidor y se aplican al neto exportado sin duplicarse al recalcular. Los cargos automáticos de venta interna no se añaden a la exportación. Se conserva el descuento ya registrado aunque cambie la configuración global.

La compilación se ejecutó con la autorización expresa del usuario («Puedes ejecutar el build esta vez»), conforme a [frontend-build](../.codex/skills/frontend-build/SKILL.md). La aceptación de interfaz se realizó con el bundle regenerado y no sólo con métodos/templates en memoria.

## Verificación

- `ExportInvoiceTest`: seis payloads (FOB/CIF/EXW × USD/VES), IVA cero, descuentos/cargos, snapshots inconsistentes, servicios y rechazo temprano de API, identidad/país/direcciones extranjeras y unicidad.
- `FiscalEmissionSchemaTest`: MySQL temporal con esquema inicial/semillas, rollback/repetición, matriz de exportación, commit exterior/rollback, envío único, snapshots congelados y edición con pagos conservados/nuevo INCOTERM. Dos procesos PHP compiten por editar y enviar una exportación preparada; se comprueba una sola transmisión, correspondencia del payload con la versión finalmente guardada, conservación de cobros y actualización del inventario según la edición aceptada.
- `tests/js/export-invoice-ui.test.cjs` y `fiscal-row.test.cjs`: cálculo de exportación, descuentos y cargos sin duplicación, cambios de categoría/INCOTERM, respuesta obsoleta de búsqueda, hidratación de edición con tasa/vencimiento/cobros originales y compilación de templates/scripts.
- `tests/js/hka-pdf-ui.test.cjs`: bloqueo de formatos antes de cargar el documento y durante su carga, evitando abrir una URL de impresión con ID nulo; conserva las descargas existentes de web, POS y finanzas.
- Regresiones de identidad, catálogos, IVA interno, autorización, edición, transporte, respuestas y PDFs HKA.

Comprobaciones ejecutadas: suite focalizada PHP (164 pruebas, 1839 aserciones), esquema inicial arriba/abajo en dos ciclos, y suite final de JavaScript: 117 pruebas aprobadas, incluidas las 15 de formulario/cálculo de exportación. La última ejecución MySQL pasó las pruebas de exportación, envío después del commit/recuperación y cobros/retenciones concurrentes: 3 pruebas, 444 aserciones. Incluye la competencia entre edición web de exportación y envío con los listeners reales de inventario. Sintaxis PHP y `git diff --check` sin errores. Las advertencias preexistentes de esquema PHPUnit y parámetros opcionales de Item no impidieron la ejecución.

Tras cerrar la afectación EXP en alta/importación de productos, pasaron 101 pruebas focalizadas con 740 aserciones. La suite unitaria completa del 11 de octubre ejecutó 634 pruebas y 14863 aserciones: conserva dos fallos previos en `QuotationStorefrontCorrelativeTest`, que espera COT-12 cuando el modelo/FiscalIdentity devuelve COT-00000012; esos tres archivos son idénticos a HEAD. Las 15 omisiones incluyen las pruebas MySQL optativas, ejecutadas aparte para este cambio. Se actualizó el conteo de semillas a 957 por la fila EXP. El skill de importación también pasó su validación.

La última regresión focalizada de exportación, identidad y PDFs pasó con 99 pruebas y 1222 aserciones. El build final terminó correctamente y el navegador cargó `app-abad4d02.js`; su hash y los de las fuentes están en el registro de interfaz.

La matriz real DEMO terminó con seis documentos confirmados y cero intentos de correo. Se usó una base MySQL temporal y un salto de numeración de 1000 respecto al tenant de referencia, cuyas credenciales se consultaron sin modificar su esquema o ventas.

| INCOTERM | Moneda Pro9 | Número / serie vacía | Control HKA | Total documental | Valor exportado VES |
|---|---|---:|---|---:|---:|
| FOB | VES | 1027 | 00-00000038 | 1.00 | 1.00 |
| FOB | USD | 1028 | 00-00000039 | 1.00 | 40.12 |
| CIF | VES | 1029 | 00-00000040 | 1.00 | 1.00 |
| CIF | USD | 1030 | 00-00000041 | 1.00 | 40.12 |
| EXW | VES | 1031 | 00-00000042 | 1.00 | 1.00 |
| EXW | USD | 1032 | 00-00000043 | 95.00 | 3811.73 |

El caso EXW USD usa una mercancía de 100.00, descuento global de 10.00, cargo global de 5.00 y pago de 95.00; la tasa es 40.12345678. Los intentos rechazados anteriores no asignaron control. No se repitieron documentos confirmados. La evidencia resumida y los hashes de archivos se conservan en [el registro de aceptación](evidencias/exportacion_hka_demo_2026_10_09.json).

Se descargaron los seis A4 originales y sus seis conversiones A5; se verificaron control e identificación extranjera en sus textos. Se generaron los seis tickets locales 80MM con QR y se inspeccionaron visualmente muestras FOB en VES/USD y EXW en USD. Los cinco primeros tickets se regeneraron con los controles/enlaces ya confirmados, HTTP denegado y transacción comercial revertida; no se emitieron nuevas facturas. En el ticket se corrigió el resumen fiscal duplicado: un pie con su propio cierre body hacía insertar dos veces tasa, IVA y total VES. La prueba de render acredita que ahora aparecen una vez.

Limitaciones verificadas:

- El PDF original HKA conserva sus rótulos genéricos «FACTURA», «RIF» y «Exento», y la referencia gráfica a IVA 16 % con importe cero. No imprime el INCOTERM en estas muestras; Pro9 sí lo conserva y lo muestra en detalle/ticket. En A4 el pie de HKA tapa parte de la tasa; la conversión A5 deja visible ese texto. El original no se modifica.
- La cuenta DEMO imprime el emisor registrado en HKA; el ticket usa el emisor congelado de la instalación local de prueba. La matriz DEMO no acredita la concordancia del RIF con una futura cuenta de producción.
- Un intento a crédito sin cobros fue rechazado por HKA con 203/1015 por ausencia de formasPago. No se inventó una forma de pago ni se registró un cobro ficticio. La aceptación acreditada corresponde a contado con forma de pago real; crédito sin cobros queda pendiente de un contrato confirmado con HKA.

## Aceptación en navegador del 11 de octubre

Se usó una instalación temporal separada, sin modificar ventas ni esquema del tenant de referencia. Desde la interfaz se dio de alta y editó un comprador ND/RUT de Chile con domicilio extranjero y territorio venezolano vacío. Se creó una factura FOB en USD con un bien de 100.00, descuento 10.00, cargo 5.00 y cobro de 95.00. La persistencia conservó exportación neta 95.00, IVA cero, tasa 40.12345678, equivalente VES 3811.73, saldo cero y una única salida de inventario.

Tras preparar localmente la operación, se editó la misma factura a CIF desde el formulario web. Se conservaron serie/número, el pago existente, la tasa y el stock; se renovó el UUID y quedó Sin solicitar, con envío HKA explícito y PDFs bloqueados hasta confirmación. El snapshot del comprador permaneció congelado al editar únicamente la ficha del cliente y se renovó al guardar explícitamente la factura. La captura del formulario está en [la evidencia visual](evidencias/exportacion_ui_2026_10_11.png).

Para comprobar el estado confirmado y las descargas sin repetir una emisión, se restauró únicamente en esa instalación temporal el snapshot de la aceptación EXW USD nº 1032/control 00-00000043 y sus A4/A5 originales, con credenciales sintéticas. El diálogo mostró la aceptación y el control, y sus botones descargaron A4, A5 y ticket 80MM al navegador. Los SHA-256 de A4/A5 coinciden con la evidencia DEMO del 9 de octubre. El ticket conserva EXW, identificación externa, importe y QR; se decodificó el QR y coincide con la URL de consulta conservada. Esta restauración acredita interfaz y descargas locales; la aceptación fiscal real corresponde a la matriz del 9 de octubre. No se hicieron nuevas emisiones ni intentos de correo al retomar.

Se verificó la barrera de descarga tanto durante la carga como para la factura editada aún pendiente. La [captura de descargas](evidencias/exportacion_descargas_ui_2026_10_11.png) y el [registro saneado de interfaz](evidencias/exportacion_ui_2026_10_11.json) conservan la evidencia. Al terminar se retiraron la base temporal, su usuario MySQL, los tres registros centrales y sus archivos de tenant; se verificó su ausencia. No quedan datos comerciales de esta prueba en la instalación operativa.

Esta implementación no declara homologación ni habilita producción.
