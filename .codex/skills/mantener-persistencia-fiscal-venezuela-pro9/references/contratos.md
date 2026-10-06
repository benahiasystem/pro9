# Contratos y puntos de intervención

## Código que mantiene el contrato

Rutas relativas a la raíz de Pro9:

| Componente | Responsabilidad |
|---|---|
| `app/CoreFacturalo/Facturalo.php` | Guardado/actualización comercial común y transacción tenant. |
| `app/Services/Fiscal/FiscalDocumentPersistence.php` | Emisor, líneas, resumen, relaciones fiscales, pagos, IGTF, retenciones y reversión. |
| `app/Services/Fiscal/FiscalAmounts.php` | Conversión USD/VES, precisión vigente y clasificación IGTF. |
| `app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php` | Traducción de pagos de la API de creación. |
| `app/Http/Controllers/Tenant/DocumentPaymentController.php` | Cobros posteriores y reversión. |
| `app/Http/Controllers/Tenant/DocumentFiscalController.php` | Autorización de ajustes, retenciones, adjuntos y preparación HKA. |
| `app/Services/Fiscal/HkaEmissionPreparation.php` | Operación/payload congelado y control validado interno. |
| `app/Models/Tenant/Document.php` | Relaciones y saldo documental. |

Al modificar un importe, seguir también sus consumidores en caja, `modules/Finance`, `modules/Report`, `modules/Dashboard` y templates PDF. No asumir que el importe aplicado equivale a efectivo recibido.

## Tablas y relaciones

| Tabla | Datos conservados |
|---|---|
| `documents` | Emisor obligatorio; moneda, tasa/fuente/fecha; cliente/sucursal; total y numeración local. |
| `document_items` | Snapshot del artículo/unidad y `iva_rate`; base, porcentaje e IVA existentes. |
| `document_payments` | Principal aplicado/recibido, moneda/tasa/fuente/fecha, impuesto, clasificación/exención, UUID, medio, recibo derivado y reversión. |
| `document_taxes` | Tributo, base/porcentaje/importe documental y VES; vínculo IGTF al pago. |
| `document_currency_totals` | Totales VES y desglose de impuestos por alícuota. |
| `document_received_retentions` | Comprobante IVA/ISLR, agente/snapshot, concepto, base/porcentaje/sustraendo, moneda/tasa, aplicación y adjunto. |
| `document_guarantee_funds` | Fondo comercial separado y aplicación al saldo. |
| `document_fiscal_data` | Catálogos/snapshots de proveedor/transacción/régimen, tercero y datos condicionales. |
| `document_emissions` | Operación única, contrato/payload congelado, estado y campos para futura respuesta/control/autorización/archivos. |

Las relaciones nuevas se crean en `000330`–`000335`; las FKs están en `2026_08_17_000999_add_tenant_foreign_keys.php`. Mantener las restricciones de unicidad de pagos, cargo IGTF, recibo derivado, retenciones y relaciones únicas documentales.

Campos retirados de `documents`: `ubl_version`, `perception`, `total_unaffected`, `total_free`, `total_igv_free`, `retention`, `user_rel_subscription_plan_id`. Conservar `user_rel_suscription_plan_id`, utilizado por suscripciones. El snapshot del artículo no contiene `cod_digemid`. No restaurar las claves heredadas `amount_pen`/`amount_usd` del JSON de retenciones ni sus productores/consumidores.

## Endpoints locales

Verificar el contrato vigente en `routes/web.php`, `routes/api.php`, requests y controladores antes de modificarlo. Las rutas API tienen prefijo `/api`.

- `POST /document_payments`: cobro posterior; `GET /document_payments/records/{id}` y `/document_payments/document/{id}`: consulta; `DELETE /document_payments/{id}`: reversión con motivo.
- `GET /documents/retention/{id}`, `POST /documents/retention`, `POST /documents/retention/upload`: comprobantes recibidos; adjuntos PDF/JPG/PNG de hasta 5 MB.
- `POST /documents/{id}/prepare-hka`: preparación administrativa, sin HTTP al proveedor.
- Web `GET/POST /companies/igtf`: configuración operativa; escritura administrativa auditada.

El pago posterior utiliza `document_id`, `date_of_payment`, `payment_method_type_id`, `payment_destination_id`, `currency_type_id`, `original_amount`, `exchange_rate`, `exchange_rate_source`, `exchange_rate_date`, `operation_key`, `igtf_status` y `exemption_reason` cuando corresponde. En la API de creación, `pagos` conserva `monto`, `codigo_metodo_pago` y `codigo_destino_pago`, con traducción de los datos fiscales nuevos. No confundir el POST web del formulario con el contrato externo de creación por API.

La creación admite `received_retentions`, `guarantee_fund`, `taxes` identificados y `fiscal_data`. Las pantallas incorporan clasificación IGTF y comprobantes recibidos; los selectores de moneda propia/cobro mixto no se implementaron en esta etapa.

## Verificaciones

- `tests/Unit/FiscalEmissionSchemaTest.php`: MySQL temporal, instalación/persistencia y concurrencia real con `tests/Support/fiscal_payment_concurrency_worker.php`.
- `tests/Unit/FiscalApiPaymentTransformTest.php`: compatibilidad del pago original, moneda propia, UUID y conversión con tasa explícita.
- `tests/Unit/FiscalLineCalculationTest.php`: cálculo de líneas en servidor.
- `tests/Unit/DocumentFiscalAuthorizationTest.php`: sucursal, administrador y autenticación.
- `tests/Unit/HkaPayloadBuilderTest.php` y `tests/Fixtures/Hka`: fixtures saneados y condiciones de preparación.
- `tests/js/fiscal-payment-ui.test.cjs`: clasificación y controles de cobro en fuentes.

Los datos ficticios `MOCK-` y los resultados históricos del informe no equivalen a pruebas de producción ni a homologación.
