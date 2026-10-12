# Retenciones IVA recibidas — Pro9 / HKA

Implementación del 12 de octubre de 2026 para instalaciones nuevas. Incluye previsión, comprobante recibido, aplicación local, consulta, recuperación y reversión auditada. Un comprobante IVA activo por factura; los anteriores permanecen en el historial. ISLR conserva su circuito local. La emisión de comprobantes IVA a proveedores (`05` HKA) no forma parte de este cambio.

## Límite real encontrado en HKA

**El cobro neto por una retención prevista no puede emitirse actualmente en HKA DEMO.** La API exige que la suma de las formas de pago coincida con el total de la factura. Las pruebas VES/USD devolvieron `203 / 1016` tanto al contado como a crédito con un abono real. No se completaron esos pagos con importes ficticios.

La venta, abonos, inventario y saldo comercial pueden guardarse. La preparación y transmisión HKA de esa combinación quedan bloqueadas antes del HTTP fiscal, con un diagnóstico visible y `can_send=false`. `config/iva_retention.php` mantiene `demo_net_collection_verified=false`; cambiarlo requiere primero evidencia real de aceptación del mismo contrato. Los tests de transporte simulado habilitan esa opción solamente en sus aplicaciones temporales.

Se validó aparte el protocolo `AplicarRetencion` mediante facturas de control completamente cobradas: POST, GET con cuerpo JSON, DELETE y consulta posterior aceptados en VES/USD. Es una prueba del transporte y conciliación; no acredita el circuito comercial del cobro neto, la condición tributaria del emisor ni producción.

Evidencia saneada:

- `evidencias/retenciones_iva_transporte_demo_2026_10_12.json`: rechazo del cobro neto al contado.
- `evidencias/retenciones_iva_credito_demo_2026_10_12.json`: rechazo del cobro neto a crédito.
- `evidencias/retenciones_iva_control_transporte_demo_2026_10_12.json`: aplicación, coincidencia exacta y eliminación de las retenciones de control.

La ausencia real responde HTTP 200, código `203`, mensaje «Retención no encontrada» y validación específica «No se ha aplicado retención previamente por lo que no se puede consultar nada». Sólo ese sobre exacto o un resultado 200 explícitamente vacío acreditan ausencia; otro rechazo o JSON incompleto no autoriza transmitir.

## Flujo y cobertura

| Entrada | Implementación |
|---|---|
| Facturación Web | Componente IVA en la factura y acción de gestión desde su cierre/listado. |
| POS y Garage/Vende Ya | Componente en pago normal y rápido, objetivo de cobro neto y diálogo compartido después de guardar. |
| Pagos posteriores | Acción IVA y actualización del saldo sin eliminar los abonos en edición. |
| Hotel, pedidos, servicio técnico y suscripciones | Componente sobre el borrador que se envía al guardado común y acceso desde las opciones de factura. |
| Cotizaciones, notas de venta y órdenes de entrega | Conversión al formulario común; el comprobante se vincula a la nueva factura. Cambiar el borrador a Nota de venta descarta los datos IVA específicos de la factura. |
| Restaurante | Usa los consumidores y opciones POS compartidos. |
| Ecommerce y facturación automática/masiva | Facturas resultantes cubiertas por el servicio común y endpoints posteriores autenticados; no se fabrican comprobantes ni se conceden permisos de registro al comprador público. |
| API y MobileApp | Contrato de creación ampliado, resumen en creación/detalle/listado y endpoints compartidos. |

La previsión es informativa: una factura 116, IVA 16, previsión 12 y cobro 104 conserva saldo 12. Sólo un comprobante válido aplica saldo. La variante digital queda pendiente de emisión por la limitación HKA descrita arriba.

Un comprobante cargado antes de obtener control queda `pending_validation`, con importe aplicado cero. La confirmación fiscal permite cotejar número/control y revalidar los datos. Una discrepancia queda `invalid`, sin efecto financiero. La aplicación pasa a `applied`; conserva perfiles/propuesta/contexto/actor y la previsión original en su auditoría. La reversión pasa a `reversed`, conserva importe original y aplicación previa en el contexto, y devuelve su efecto al saldo.

La condición IVA exige los perfiles versionados correspondientes: proveedor ordinario, cliente con facultad vigente, ámbito de operación y evidencia para 100 %. SPE, firma personal y RIF se acreditan separadamente. No se siembran condiciones supuestas.

## Datos, dinero y recuperación

- `document_fiscal_data.iva_retention_prevision`: propuesta calculada en servidor; se recalcula en edición y no se suma a pagos ni impuestos.
- `document_received_retentions`: UUID/hash de solicitud, estado de aplicación, solicitud privada, base imponible fiscal separada de IVA, diagnóstico y reversión. Índices generados limitan un IVA activo y permiten reemplazar un número ya revertido manteniendo ambas versiones.
- `document_iva_retention_operations`: acción, payload/contexto congelados, actor, UUID de intento, contador de mutaciones y fallos, estado y próxima ejecución.
- `received_retention_uploads`: token privado por usuario/tenant, vencimiento de 24 horas y consumo único al vincularlo a la factura. Un rollback conserva el origen temporal para recuperar la solicitud.

`base` es IVA sujeto a retención; `taxable_base` es la base imponible fiscal correspondiente. No son intercambiables. La validación compara IVA y retención con el valor fiscal VES conservado de la factura. `applied_amount` liquida saldo en la moneda documental usando la tasa del comprobante. El payload HKA convierte directamente de moneda del comprobante a moneda fiscal; VES→VES conserva el importe aunque la factura sea USD y las tasas difieran. Las tasas mantienen ocho decimales y los importes se redondean a dos.

Con varias alícuotas, un comprobante que cubre todo el IVA conserva la base imponible completa. Se rechaza una base IVA parcial sin desglose de partidas: el IVA parcial por sí solo no identifica qué base corresponde a cada alícuota. Con una alícuota única, la proporción se determina desde los importes conservados.

Ejemplo probado: IVA fiscal 320 VES, retención 240 VES, factura USD con tasa de emisión 10 y tasa del comprobante 20. HKA recibe IVA 320 y retención 240 VES; se aplican 12 USD al saldo. No se vuelve a convertir ese importe fiscal con la tasa de emisión.

Las retenciones aplicadas reducen saldo y actualizan la condición del crédito sin generar pagos globales ni movimientos de efectivo. Pendientes, inválidas y revertidas aplican cero; finanzas/dashboard conservan sus sumas sobre `applied_amount`. Los PDFs locales omiten aplicaciones inactivas y muestran la previsión informativa cuando existe. Los originales HKA y el payload de la factura permanecen intactos.

El orden de bloqueo es empresa → factura → comprobante → operación. El HTTP ocurre fuera de transacciones. Cada mutación exige lectura previa y conciliación posterior. Una coincidencia remota existente se adopta como confirmada, aun sin POST local. Una respuesta incompleta queda incierta; otro comprobante/identidad queda en conflicto y se detiene la sobrescritura.

La reversión con transmisión/presencia posible conserva el saldo aplicado hasta acreditar ausencia remota. Espera el cierre del intento anterior (gracia de 90 segundos); un intento tardío/cancelado no puede finalizar otra operación. Las operaciones nunca transmitidas ni observadas remotamente se revierten localmente. Motivo, actor y UUID de reversión son obligatorios.

La consulta manual permite verificar un conflicto después de corregirlo externamente. Una ausencia posterior al conflicto de aplicación conserva el bloqueo de reenvío; una ausencia acreditada para la reversión sí completa la restitución local. La limpieza temporal toma el mismo bloqueo del upload que su consumo, conservando los soportes vinculados.

`tenancy:run iva-retentions:recover` está incorporado al scheduler por minuto, sin superposición. Recupera solicitudes durables con espera progresiva hasta una hora y limpia uploads vencidos/copias huérfanas propias. Rechazos definitivos y conflictos requieren revisión; no se retransmiten automáticamente. Cambiar identidad, ambiente o credenciales detiene una operación preparada bajo otro contexto.

## API

Las rutas Web usan sesión/CSRF y las API añaden `/api`, con autenticación tenant. Consulta/descarga exigen sucursal; registro requiere `create_payment` y reversión `delete_payment` o administrador. El reintento de eliminación usa el permiso de reversión.

| Método y ruta | Contrato |
|---|---|
| POST `documents/retention/proposal` | `customer_id` o `document_id`, fecha, moneda, `total_igv`, `total_taxed` y contexto. Propuesta informativa y aviso de bloqueo del cobro neto HKA. |
| POST `documents/retention/upload-draft` | Multipart `file`; devuelve `upload_id` y vencimiento. PDF/JPG/PNG, hasta 5 MB. |
| POST `documents/retention/upload` | Ruta conservada para soporte de una factura existente: `document_id` y `file`. |
| GET `documents/retention/{document}` | Registros y resumen IVA, previsión, saldo y control real. |
| POST `documents/retention` | Registro IVA con `document_id`, UUID y datos del comprobante. |
| GET `documents/retention/{id}/attachment` | Descarga autorizada privada, adjunta y sin caché. |
| POST `documents/retention/{id}/query-hka` | Consulta/conciliación; no envía POST/DELETE al proveedor. |
| POST `documents/retention/{id}/retry-hka` | `operation_key` de la operación HKA vigente; vuelve a consultar antes de transmitir. |
| POST `documents/retention/{id}/reverse` | UUID nuevo y `reason`; respuesta puede conservar reversión pendiente de conciliación. |

Previsión opcional en creación/edición de factura:

```json
{"iva_retention_prevision":{"enabled":true,"fiscal_context":{}}}
```

Comprobante IVA sobre una factura existente, o elemento de `received_retentions` al crearla (sin `document_id` en este último caso):

```json
{
  "document_id": 100,
  "tax_kind": "IVA",
  "operation_key": "16d50631-2d84-44b6-90ac-52b93c52c105",
  "voucher_number": "20261000000001",
  "voucher_date": "2026-10-12",
  "agent_rif": "J-12345678-9",
  "invoice_number": "FF01-00000100",
  "invoice_control": "00-00000100",
  "base": "16.00",
  "percentage": "75",
  "amount": "12.00",
  "subtrahend": "0",
  "currency_type_id": "VES",
  "exchange_rate": "1.00000000",
  "upload_id": "248a8387-5b24-490d-821c-9573e013a20e",
  "fiscal_context": {}
}
```

Datos sintéticos: el RIF debe coincidir con el cliente y su perfil real vigente. Como alternativa a `upload_id`, se admite el `attachment` emitido por la carga vinculada a esa factura. No se aceptan rutas ajenas.

Repetir el mismo UUID y datos devuelve el registro original; reutilizarlo con datos distintos o para otra acción produce HTTP **409**. Los datos inválidos conservan HTTP **422**. Los reemplazos usan un UUID nuevo. El resumen público `iva_retention` incluye previsión, registros, importes aplicado/original, estados, diagnóstico y permisos. No expone payloads, contexto de conexión, hashes privados ni la solicitud congelada.

Reversión (`POST /api/documents/retention/25/reverse`):

```json
{"operation_key":"a15baae0-f764-43ef-94eb-f9bbbec4333d","reason":"Sustitución de comprobante incorrecto"}
```

Consulta (`POST /api/documents/retention/25/query-hka`) con `{}`. Reintento (`POST /api/documents/retention/25/retry-hka`) con el `hka_operation_key` del resumen, sin generar otro UUID:

```json
{"operation_key":"16d50631-2d84-44b6-90ac-52b93c52c105"}
```

El cliente debe conservar sus UUID hasta recibir una respuesta y consultar el registro ante pérdida de conexión. El reintento respeta una reversión/operación más reciente y consulta HKA antes de cualquier mutación. En carga durante la creación, primero subir el soporte mediante `upload-draft`, luego añadir el comprobante a `received_retentions` en el contrato habitual de creación. La previsión se envía aparte; ni ella ni el comprobante se añaden a `payments`.

## Verificación y despliegue

Pruebas en MySQL temporal: esquema/seeding/FKs/rollback/repetición; cobro neto local, VES/USD, soporte anterior al guardado y rollback, idempotencia, sustitución del mismo número, dos procesos concurrentes, referencias discrepantes, permisos, vencimiento, fallos inciertos, adopción de registro remoto y reversión conciliada. Pruebas puras verifican cuerpos JSON GET/DELETE, TLS/timeouts, bloqueo 1016 y separación de importes fiscales/aplicados. Las suites JS y PDF verifican componentes compartidos, propuestas obsoletas y presentación documental.

Las comprobaciones finales incluyen registro frente a cobro, dos reversiones del mismo UUID y reversión frente a cobro en procesos independientes. El restablecimiento del crédito no crea filas de caja. Una falla al preparar el payload posterior a la validación local revierte esa aplicación mediante un savepoint y conserva el comprobante como inválido, con saldo intacto. En POS/Garage se conservan los abonos al reabrir y restaurar borradores; pedidos con previsión sugieren sólo el restante neto al agregar otro medio de pago.

Resultados registrados: la corrida de esquema inicial y matriz IVA pasó 7 pruebas / 1833 aserciones. Las regresiones finales PHP/PDF pasaron 53 pruebas / 1115 aserciones; los seis casos IVA refinados en MySQL pasaron en corridas de 3 pruebas / 302 aserciones, 2 / 34 y 1 / 46. Tras separar las bases de alícuotas mixtas, se repitieron previsión/caja y equivalencia fiscal: 2 pruebas / 249 aserciones, sin fallos. La suite JS final pasó 149 pruebas sin fallos. Lint PHP y `git diff --check` también pasaron. Los módulos adicionales se verificaron mediante sus componentes/contratos compartidos; no se acredita una aceptación manual completa de cada pantalla sobre un tenant nuevo. La pestaña local observada pertenece a un tenant anterior, que no se convirtió.

El esquema inicial tiene 342 tablas de aplicación y 343 migraciones tenant. No se modificaron ni convirtieron tenants existentes. Todas las bases propias de pruebas se eliminan al finalizar; los registros fiscales de control DEMO permanecen en HKA y sus retenciones de prueba fueron eliminadas/conciliadas. No se enviaron mensajes al proveedor.

La compilación de assets corresponde al usuario según `frontend-build`. Durante la verificación se actualizó externamente el manifiesto y los bundles después de las fuentes finales; el agente no ejecutó build ni editó los generados. La comprobación de fuentes/plantillas no acredita por sí sola la aceptación visual completa ni el circuito fiscal que HKA rechazó. Producción continúa fuera del alcance.
