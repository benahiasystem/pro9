# Persistencia fiscal de Venezuela y emisión HKA DEMO

## Alcance implementado

El esquema tenant consolidado y el guardado común de Facturas, notas de crédito y débito conservan los datos fiscales utilizados. La implementación conserva la preparación de facturas/notas y, desde el 7 de octubre de 2026, envía automáticamente las facturas digitales DEMO después del commit comercial. Producción, emisión de notas, anulación remota y descarga HKA quedan fuera. Se reutiliza el esquema existente, sin migraciones ni backfills.

Se conservaron los identificadores, ambiente/modalidad, numeración local, snapshots comerciales, anticipos, descuentos, cargos, vencimientos, cuotas y relaciones. Los nombres internos `igv` continúan representando IVA. `total_exportation` e `invoices.operation_type_id` permanecen disponibles, con preparación de exportaciones bloqueada.

## Esquema final

| Tabla | Persistencia nueva |
|---|---|
| `documents` | `issuer` JSON obligatorio obtenido en servidor; fuente y fecha de `exchange_rate_sale`. |
| `document_items` | `iva_rate` JSON con código HKA y porcentaje aplicado; unidad HKA en el snapshot del artículo. |
| `document_payments` | Moneda recibida, importe original neto de vuelto, tasa/fuente/fecha, IGTF y motivo de exención, UUID único, snapshot del medio, importe fiscal recibido y trazabilidad de reversión. |
| `document_taxes` | IVA, IGTF y otros tributos identificados con base, porcentaje e importe documental/VES; vínculo único al pago para IGTF. |
| `document_currency_totals` | Totales VES y desglose de impuestos por alícuota conservados por documento. |
| `document_received_retentions` | Comprobantes IVA/ISLR múltiples, agente y snapshot, concepto, base, porcentaje, sustraendo, importe/moneda/tasa, aplicación al saldo y adjunto. |
| `document_guarantee_funds` | Fondo separado por documento: base, porcentaje e importe aplicado al saldo. |
| `document_fiscal_data` | Proveedor, transacción, régimen, snapshots de catálogos, tercero y datos condicionales; relación única. |
| `document_emissions` | Proveedor HKA, ambiente, versión contractual, UUID, payload congelado, estado, respuesta, control/autorización, fechas y referencias de archivo; relación única. |
| `companies` | `igtf_enabled` deshabilitado inicialmente y `igtf_rate` nullable. |
| `fiscal_configuration_audits` | Valores anteriores/nuevos de los ajustes operativos de IGTF, actor y fecha. |

Las seis tablas nuevas se crean en las migraciones `000330`–`000335`. Las claves foráneas están en la migración final `000999`, incluidas las relaciones con catálogos, usuarios y comprobantes. Se conservan restricciones únicas para documento fiscal/emisión/totales/fondo, operación de pago, recibo derivado IGTF y comprobante recibido por documento/tipo/agente/número.

Los importes, bases y porcentajes nuevos usan los equivalentes actuales `decimal(12,2)`. Las tasas nuevas usan `decimal(13,3)`, como `exchange_rate_sale`, cuya definición no cambió. Se conserva el tratamiento numérico y redondeo existentes. Las conversiones usan VES por USD y la tasa conservada con tres decimales; el adaptador sólo formatea la representación exigida por HKA.

## Campos retirados

De `documents`: `ubl_version`, `perception`, `total_unaffected`, `total_free`, `total_igv_free`, `retention` y `user_rel_subscription_plan_id`. Continúa `user_rel_suscription_plan_id`, utilizado por suscripciones.

Se retiró `cod_digemid` del snapshot persistido de `document_items.item`. El JSON peruano de retenciones y sus claves `amount_pen`/`amount_usd` desaparecieron del flujo de facturas. POS, facturación, conversiones y suscripciones dejan de calcular retenciones estimadas. Los PDFs y reportes de facturas dejaron de consumir los tratamientos retirados. Los formatos contables externos con posiciones fijas conservan una casilla vacía/cero para el tratamiento retirado; no leen una columna de la factura. Otros modelos comerciales mantienen sus contratos propios.

## Guardado, saldos y cobros

`Facturalo::save` y `update` centralizan el guardado transaccional usado por los canales existentes. El servidor recalcula las líneas y los totales; obtiene emisor, identidad del cliente y sucursal; guarda alícuotas y equivalencias. Las modificaciones de empresa, cliente o catálogos no reconstruyen documentos anteriores. Una factura con historial de pagos o retenciones contabilizados, o con una emisión preparada, no se edita directamente.

`payment` es el principal neto aplicado en la moneda de la factura. `original_amount` corresponde al principal efectivamente recibido, sin vuelto ni IGTF; `tax_amount` corresponde al IGTF recibido en esa moneda. Caja, bancos, movimientos web/móviles y exportaciones distinguen esa recepción del importe aplicado. La reversión conserva el registro y lo excluye de los pagos activos. Los saldos descuentan pagos activos, retenciones aplicadas y fondos de garantía; el indicador `total_canceled` se sincroniza con ese saldo.

El IGTF inicial aumenta el total de la factura como impuesto separado. Su liquidación se conserva como recibo interno vinculado al pago principal mediante `receipt_parent_id`, con recepción de efectivo cero. El IGTF de un pago posterior crea una Nota de débito local `08`, motivo `IGTF`, transacción HKA `98`: sin artículos, IVA ni movimiento de inventario. El recibo derivado liquida esa nota sin duplicar el ingreso de caja. Las restricciones y los bloqueos transaccionales impiden repetir el pago o el cargo.

La reversión de un pago inicial mantiene el cargo fiscal de la factura y su trazabilidad. Una nota local IGTF aún no preparada se anula al revertir el pago. Revertir una nota ya preparada requiere la etapa posterior de ajuste/anulación fiscal; esta etapa rechaza esa operación y revierte la transacción completa.

Los comprobantes recibidos reducen el saldo sin cambiar el total de venta ni producir efectivo. El agente debe ser el cliente de la factura; el concepto ISLR debe existir. La base de retención IVA corresponde al IVA de la factura. Se valida la fórmula base × porcentaje − sustraendo, el IVA disponible, el saldo y la pertenencia del archivo al almacenamiento privado del tenant.

## Contratos API y controles

Los endpoints siguientes existen bajo el tenant autenticado. En API llevan el prefijo `/api`; en web mantienen las rutas comerciales.

- `POST /document_payments`: registrar pago; `GET /document_payments/records/{id}` y `/document_payments/document/{id}`: pagos y saldo; `DELETE /document_payments/{id}`: reversión con motivo.
- `GET /documents/retention/{id}`, `POST /documents/retention` y `POST /documents/retention/upload`: consultar/registrar comprobantes y adjuntar PDF/JPG/PNG, máximo 5 MB.
- `POST /documents/{id}/prepare-hka`: preparar la única operación fiscal; requiere administrador tenant.
- Web: `GET/POST /companies/igtf`, con escritura reservada al administrador y auditoría de valores.

Ejemplo de pago en USD para una factura VES:

```json
{
  "document_id": 123,
  "date_of_payment": "2026-09-10",
  "payment_method_type_id": "01",
  "payment_destination_id": "cash",
  "currency_type_id": "USD",
  "original_amount": 10,
  "exchange_rate": 10.123,
  "exchange_rate_source": "manual",
  "exchange_rate_date": "2026-09-10",
  "operation_key": "f17edcc8-a8c9-4a9b-918d-0cfb555aa4ab",
  "igtf_status": "subject"
}
```

La configuración IGTF debe estar habilitada y tener una tasa positiva. La recepción total será principal más IGTF; la factura recibe sólo el principal y el cargo posterior queda en la nota vinculada. `exempt` exige `exemption_reason`; `not_applicable` mantiene el comportamiento sin IGTF.

Si se omite moneda propia, se mantienen moneda y comportamiento del documento. En los `pagos` de la API de creación se conservan `monto`, `codigo_metodo_pago` y `codigo_destino_pago`, y se aceptan los campos nuevos del ejemplo. Una moneda propia exige UUID; una moneda distinta exige importe original y tasa explícitos. La fuente por defecto es `manual` y la fecha de tasa por defecto es la fecha de pago.

La creación también admite `received_retentions`, `guarantee_fund`, `taxes` identificados y `fiscal_data`. No se agregaron selectores de cobro mixto a las pantallas. Sí se agregaron clasificación IGTF y registro de comprobantes reales. Los usuarios operan sobre su sucursal; crear pagos/retenciones requiere permiso de cobro y revertir requiere permiso de eliminación de pagos, con acceso administrativo conservado.

`control_number` continúa siendo texto nullable, separado de `series`/`number`. Los contratos comerciales no lo asignan. `HkaEmissionPreparation::setControl` valida formato, inmutabilidad y duplicidad bajo bloqueo; no tiene endpoint comercial público.

## Preparación HKA

`HkaPayloadBuilder` es puro y no usa HTTP ni consulta catálogos. Construye comprador, líneas, pagos, impuestos, totales VES y referencias exclusivamente desde los snapshots entregados. Traduce `01/07/08` de Pro9 a `01/02/03` de HKA sin alterar la numeración local. Las notas conservan serie, número, fecha, total y control de la factura afectada; la preparación exige control real de la referencia.

Contrato publicado: https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json

La copia versionada está en `app/Services/Fiscal/contracts/hka-ve-v1.json`, con SHA-256 `f2ddb07c239b04319df1fc9b1e6b4db137f89b51f063fa4989fd1bc836d10f04`. La operación conserva `hka-ve-v1:<hash>`. Los fixtures saneados de Factura, crédito, débito e IGTF están en `tests/Fixtures/Hka`; el validador local comprueba estructura, campos requeridos, listas, límites, patrones y enumeraciones del contrato.

Se almacenan los estados `not_requested`, `prepared`, `pending`, `confirmed`, `rejected`, `uncertain`, `cancelled`; la preparación produce los dos primeros; el envío DEMO también produce `pending`, `confirmed`, `rejected` y `uncertain`. Preparar dos veces devuelve la misma operación/payload. Falta de equivalencias, tributos adicionales sin adaptación HKA, exportaciones, regímenes especiales, terceros u otras combinaciones no implementadas generan un error explícito. Sus datos condicionales pueden conservarse para la siguiente etapa, sin inventar códigos.

No se guardan credenciales/JWT en snapshots ni operaciones; se rechazan claves sensibles anidadas en datos fiscales. El Swagger no incluye un nodo de identidad del emisor: su RIF permanece en `documents.issuer`; el transporte posterior debe comprobar que las credenciales utilizadas correspondan al emisor conservado. No se restauraron perfiles, reservas ni asignaciones anticipadas retiradas.

## Evidencia de verificación

| Comprobación | Resultado |
|---|---|
| `vendor/bin/phpunit tests/Unit` en `pro9-php` | 490 casos, 13.537 aserciones, sin fallos; 10 casos MySQL omitidos en esta ejecución y ejecutados aparte. |
| `FiscalEmissionSchemaTest`, MySQL temporal con `PRO9_FISCAL_MYSQL_TESTS=1` | 3 pruebas, 1.750 aserciones, sin fallos. Incluye procesos concurrentes reales. |
| Revisión final de `test_fresh_tenant_migrations_seed_rollback_and_repeat` después del ajuste de tasa de edición | 1 prueba, 1.535 aserciones, sin fallos; dos instalaciones idénticas, rollback y precisión de tasa/conversión. |
| `SeriesMySqlConcurrencyTest`, MySQL temporal | 7 pruebas, 54 aserciones, sin fallos. |
| `node --test tests/js/*.test.cjs` | 36 pruebas, sin fallos; incluye cálculo real de líneas y comportamiento de clasificación IGTF. |
| PHP lint | 86 archivos PHP modificados/nuevos, sin errores. |
| Fuentes Vue | Scripts y templates de 23 componentes modificados/nuevos analizados correctamente, sin generar assets. |
| `CurrentPdfRenderingTest` | 5 PDFs de prueba, 48 aserciones; incluye nota IGTF sin artículos/IVA. Factura y nota IGTF revisadas visualmente. |
| `CurrentPdfTemplateContractTest` | 2 pruebas, 479 aserciones; templates fiscales y reportes financieros compilados y analizados. |
| `git diff --check` | Sin errores. |

Las bases usadas por las pruebas MySQL son temporales y se eliminan al terminar. Las verificaciones no ejecutaron migraciones sobre tenants reales. PHPUnit informa de un esquema XML de configuración obsoleto; MySQL muestra una deprecación preexistente de parámetros de `Item.php`. Estos avisos no produjeron fallos.

Las pruebas abarcan persistencia de Factura/crédito/débito, pagos/cuotas, numeración, inventario y conversiones comerciales; USD/VES, tasas con tres decimales, vuelto y redondeo; IGTF inicial/posterior, exención, configuración, duplicados concurrentes y reversión; IVA/ISLR múltiples, duplicados y límite de saldo; fondos separados; preservación de snapshots y payload congelado; control duplicado; esquema/precisiones/FKs, seeding, rollback y segunda instalación idéntica.

Los PDFs A4 de Factura y nota exclusiva IGTF fueron generados y revisados visualmente. La Factura muestra emisor conservado, IGTF, retención, fondo, recepción/aplicación en monedas distintas y equivalentes VES. Los demás templates se verificaron por compilación Blade y análisis PHP. Las fuentes Vue/JavaScript se verifican sin generar assets.

La compilación frontend queda a cargo del usuario según `frontend-build`. No se modificó `public/build`. Estas verificaciones acreditan persistencia y preparación contractual; no incluyen emisión HKA real, pruebas DEMO de envío, homologación, consultas, descargas, conciliación, Órdenes de entrega ni comprobantes emitidos de retención.


## Envío automático DEMO — 7 de octubre de 2026

`Facturalo::save` registra un callback en la conexión tenant que sólo se ejecuta tras el commit exterior. Cubre las facturas `01` digitales DEMO de los consumidores comunes (web, POS y API). Un rollback descarta el callback. La factura, sus pagos y el inventario quedan guardados aunque HKA falle. La respuesta informa «Venta guardada» y añade `fiscal_emission`; no cambia `state_type_id` por el resultado remoto.

`HkaEmission` valida empresa, RIF conservado, modalidad y ambiente, y rechaza identidades serie/número ambiguas entre sucursales. Reutiliza `HkaAuthentication`, `HkaEmissionPreparation` y el payload congelado. Antes de transmitir reclama la operación bajo bloqueo empresa/documento/emisión y persiste `pending`. HTTP se ejecuta fuera de transacciones: autenticación 10 s, emisión 20 s, consulta 10 s, conexión 5 s, TLS verificado, sin redirecciones ni reintentos automáticos. No hay cola ni worker de envío.

`HkaPayloadBuilder::transactionId` transforma el UUID local a 32 caracteres hexadecimales únicamente en el borde HKA. El UUID original sigue en `document_emissions.operation_key`; la consulta y cualquier reenvío usan el identificador conservado en el payload. HKA DEMO rechazó los guiones con validación 1002, aunque Swagger no publica esa restricción.

`HkaResponse` separa HTTP, código de negocio y validaciones; sólo confirma código 200 con identidad y control coherentes. En consultas, exige el `transaccionId` exacto y un estado conocido; `Enviada` fue verificado en DEMO. Las fechas válidas de asignación se conservan en hora de Caracas. Timeout, duplicado sin conciliación, respuesta incompleta, código desconocido o conflicto de control quedan por conciliar. Una consulta fallida no deshace una confirmación previa. Las respuestas persistidas contienen metadatos y diagnósticos locales permitidos, nunca JWT, credenciales ni textos crudos del proveedor.

La consulta DEMO por un UUID nuevo devolvió HTTP 200/código 203, mensaje «Consulta no procesada», estado nulo y una única validación «Documento no encontrado en nuestra base de datos». Sólo ese sobre exacto acredita ausencia para el reintento. Se consulta de nuevo antes de reenviar, se espera al menos 30 s desde el intento y se conserva el payload/UUID. Una operación `pending` interrumpida pasa a consulta después de 40 s. Rechazos definitivos permanecen congelados; su corrección no se implementó.

Las rutas autenticadas web/API `POST documents/{document}/send-hka` y `POST documents/{document}/query-hka` devuelven el DTO fiscal mínimo. Envío manual exige administrador; consulta limita vendedores a su sucursal. `prepare-hka` también devuelve el DTO saneado, sin payload. Creación, detalle y listado incorporan estado, descripción, ambiente, control, código, diagnóstico y permisos `can_send/can_query`.

El listado carga las emisiones en lote incluso cuando Laravel envuelve los modelos en `DocumentResource`. Desde el 9 de octubre de 2026, «Estado» presenta Sin solicitar, Preparado, Enviando, Confirmado, Rechazado, Por conciliar y Cancelado cuando aplica HKA; los documentos ajenos al flujo mantienen su estado local. El rechazo local y las anulaciones tienen prioridad sobre el resultado fiscal previo. La columna «Estado HKA» se retiró; el control aparece sólo en el popup del estado, acompañado del motivo saneado en rechazados. Consultar comprobante está a la izquierda de PDF en el detalle y Enviar HKA está en Opciones, con sus permisos actuales. La edición se bloquea tras preparar. Las claves de caché incluyen tenant y versión; documento/emisión cambian la versión después del commit, también con drivers sin etiquetas. Consultar/enviar actualiza el resultado, recarga el detalle y el listado.

### Validación real en bbc.localhost

Se usó exclusivamente el tenant digital DEMO existente y sus credenciales internas, sin imprimirlas. Se registraron pruebas de servicio por 1,16 VES cada una, marcadas en la información adicional:

- ID 11, FF01-8: HKA rechazó con código 203 porque FF01 carece de un rango disponible. La consulta de numeraciones mostró un rango maestro general, serie «NO APLICA», tipo «TODOS», prefijo 00; ese dato no reemplaza las series Pro9 ni autoriza una reserva.
- ID 12, sin serie, número 1: HKA rechazó el `transaccionId` con guiones (validación 1002). El payload se conserva como evidencia; no se alteró ni se reenvió.
- ID 13, sin serie, número 2: tras corregir el adaptador, HKA confirmó código 200 y control **00-00000002**. `EstadoDocumento` por el identificador alfanumérico devolvió código 200, estado **Enviada**, número 2 y el mismo control. Pro9 conservó el UUID local y el estado comercial registrado.

Para esa prueba se añadió una configuración local sin serie, inicio 1, en la sucursal 1; FF01 conserva su configuración. No se llamó `AsignarNumeraciones`, no se recrearon perfiles/reservas y no se cambió el ambiente. Las dos pruebas rechazadas y sus pagos permanecen trazables.

La aceptación del listado se comprobó mediante su recurso/controlador con caché activa y en la sesión Chrome existente de bbc.localhost: columna Estado HKA, Confirmado, control 00-00000002 y acción Consultar HKA. Durante la sesión los assets fueron recompilados externamente; el agente no ejecutó build ni editó bundles. La comprobación visual usa esos assets actualizados. Las pruebas incluyen transporte/interpretación simulados, commit/rollback, fallos y recuperación, dos procesos concurrentes con un único envío, permisos y caché por tenant, y comportamiento Vue sin build. Estos casos DEMO no constituyen homologación de notas ni de todos los escenarios fiscales.


### Comprobaciones finales de esta etapa

- Suite fiscal/HKA y autorización seleccionada: **98 pruebas PHP, 313 aserciones**; transporte, payloads, interpretación, serialización sin datos internos, identidad/control, configuración y política local.
- MySQL temporal: esquema inicial/seeding/rollback/repetición y concurrencia de cobros pasaron; prueba específica del envío pasó con **48 aserciones**, incluyendo commit exterior, rollback, recuperación, serialización del listado y dos procesos con una sola transmisión simulada.
- UI: **5 pruebas JavaScript**; columna inicialmente visible junto al estado comercial, consulta y recarga, clics concurrentes, conservación de la fila ante errores y compatibilidad de pagos. Sintaxis Vue/PHP y `git diff --check` correctos.
- Navegador: la consulta desde la columna Estado HKA finalizó manteniendo Confirmado y control 00-00000002; el listado recargó mostrando los diagnósticos específicos de las pruebas rechazadas. La evidencia visual quedó en el directorio de visualizaciones de la sesión, sin incorporarla a los bundles.

Se observaron avisos previos del esquema XML de PHPUnit y de parámetros opcionales de `Item`; no provocaron fallos en las pruebas ejecutadas.


## Edición antes del registro HKA — 7 de octubre de 2026

La regla anterior que bloqueaba toda factura con cobros queda reemplazada por la [política de edición antes de HKA](edicion_facturas_antes_hka.md). Los cobros se conservan; la edición depende del estado comercial, el control y la conciliación fiscal. Las restricciones de reversión de cobros permanecen en su servicio específico.
