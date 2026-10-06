# Persistencia fiscal de Venezuela y preparación HKA

## Alcance implementado

El esquema tenant consolidado y el guardado común de Facturas, notas de crédito y débito conservan los datos fiscales utilizados. La implementación prepara solicitudes HKA sin enviar documentos ni consultar al proveedor. Los cambios son para instalaciones nuevas; no incluyen migraciones de bases existentes, backfills ni operaciones sobre tenants reales.

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

Se almacenan los estados `not_requested`, `prepared`, `pending`, `confirmed`, `rejected`, `uncertain`, `cancelled`; esta etapa sólo produce los dos primeros. Preparar dos veces devuelve la misma operación/payload. Falta de equivalencias, tributos adicionales sin adaptación HKA, exportaciones, regímenes especiales, terceros u otras combinaciones no implementadas generan un error explícito. Sus datos condicionales pueden conservarse para la siguiente etapa, sin inventar códigos.

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
