# Contratos y puntos de intervención

## Código que mantiene el contrato

Rutas relativas a la raíz de Pro9:

| Componente | Responsabilidad |
|---|---|
| `app/CoreFacturalo/Facturalo.php` | Guardado/actualización comercial común y transacción tenant; emisión elegible después del commit exterior al crear, sin emisión automática al editar. |
| `app/Services/Fiscal/FiscalDocumentPersistence.php` | Emisor, líneas, resumen, relaciones fiscales, pagos, IGTF, retenciones y reversión. |
| `app/Services/Fiscal/FiscalAmounts.php` | Conversión USD/VES, precisión vigente y clasificación IGTF. |
| `app/CoreFacturalo/Requests/Api/Transform/DocumentTransform.php` | Traducción de pagos de la API de creación. |
| `app/Http/Controllers/Tenant/DocumentPaymentController.php` | Cobros posteriores y reversión. |
| `app/Http/Controllers/Tenant/DocumentFiscalController.php` | Autorización de ajustes, retenciones, adjuntos, preparación, envío/consulta fiscal y rastreo de correo HKA. |
| `app/Services/Fiscal/HkaEmissionPreparation.php` | Operación/payload congelado, sin HTTP. |
| `app/Services/Fiscal/HkaEmission.php` | Reclamo de emisión, envío, conciliación y persistencia del resultado/control con protección frente a respuestas tardías. |
| `app/Services/Fiscal/HkaTransport.php` | Transporte DEMO de emisión, consulta, correo, rastreo y `DescargaArchivo` PDF; autenticación, TLS y tiempos acotados. |
| `app/Services/Fiscal/HkaResponse.php` | Interpretación de éxito de negocio, identidad/control, rechazo e incertidumbre. |
| `app/Services/Fiscal/HkaMail.php` | Intentos idempotentes y rastreo de correo, independientes del estado fiscal. |
| `app/Services/Fiscal/HkaPdf.php` | Autorización y validación compartidas de descargas; Copias HKA reutilizables y conversión A5 en memoria antes de guardar. |
| `app/Services/Fiscal/HkaPdfStore.php` | Validación y escritura atómica privada por operación y formato, filename fiscal, reutilización y recuperación de copias. |
| `app/Services/Fiscal/HkaTicketPdf.php` | Ticket existente con QR desde la URL conservada, sin HTTP ni sobrescritura del PDF local. |
| `app/Http/Controllers/Tenant/DocumentPdfController.php` | Descarga autenticada web/API y respuesta PDF adjunta sin caché. |
| `app/Services/Fiscal/DocumentEditPolicy.php` | Permiso/motivo de edición e invalidación trazable de la operación anterior. |
| `app/Services/Fiscal/DocumentEditSettlements.php` | Conservación de cobros y restricciones de importes/retenciones durante la edición. |
| `resources/js/mixins/document-email.js` | Envío, UUID, validaciones y rastreo compartidos por diálogos web/listado y POS/Garage. |
| `resources/js/mixins/document-pdf.js` | Descargas explícitas A4/A5/80MM y errores compartidos, sin fallback local para facturas digitales. |
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
| `document_emissions` | Operación única, contrato/payload congelado, estado, respuesta/control/autorización y `consulta_url` opcional procedente de HKA; `response.mail` para distribución y `response.edit_history` para trazabilidad de edición, sin nuevas tablas; PDF HKA separado en disco privado por operación/formato. |

Las relaciones nuevas se crean en `000330`–`000335`; las FKs están en `2026_08_17_000999_add_tenant_foreign_keys.php`. Mantener las restricciones de unicidad de pagos, cargo IGTF, recibo derivado, retenciones y relaciones únicas documentales.

Campos retirados de `documents`: `ubl_version`, `perception`, `total_unaffected`, `total_free`, `total_igv_free`, `retention`, `user_rel_subscription_plan_id`. Conservar `user_rel_suscription_plan_id`, utilizado por suscripciones. El snapshot del artículo no contiene `cod_digemid`. No restaurar las claves heredadas `amount_pen`/`amount_usd` del JSON de retenciones ni sus productores/consumidores.

## Endpoints locales

Verificar el contrato vigente en `routes/web.php`, `routes/api.php`, requests y controladores antes de modificarlo. Las rutas API tienen prefijo `/api`.

- `POST /document_payments`: cobro posterior; `GET /document_payments/records/{id}` y `/document_payments/document/{id}`: consulta; `DELETE /document_payments/{id}`: reversión con motivo.
- `GET /documents/retention/{id}`, `POST /documents/retention`, `POST /documents/retention/upload`: comprobantes recibidos; adjuntos PDF/JPG/PNG de hasta 5 MB.
- `POST /documents/{id}/prepare-hka`: preparación administrativa, sin HTTP al proveedor.
- `POST /documents/{id}/send-hka`: envío fiscal administrativo; `POST /documents/{id}/query-hka`: conciliación dentro del tenant/sucursal.
- `POST /documents/email`: conserva `id` y `customer_email`; para HKA añade UUID `request_id` y `resend` explícito. Facturas digitales requieren confirmación/control y DEMO, sin fallback SMTP; los demás documentos conservan correo comercial.
- `POST /documents/{id}/query-hka-email`: rastreo de destinatarios del intento, autorizado por tenant/sucursal.
- Web/API `GET /documents/{external_id}/download-pdf/{format}`: descarga autenticada por tenant/sucursal, limitada a `a4|a5|ticket`; `ticket` representa 80MM. Aplicar [distribución HKA](../../distribuir-documentos-hka/SKILL.md) para disponibilidad, validación y respuesta en memoria.
- Web `GET/POST /companies/igtf`: configuración operativa; escritura administrativa auditada.

El pago posterior utiliza `document_id`, `date_of_payment`, `payment_method_type_id`, `payment_destination_id`, `currency_type_id`, `original_amount`, `exchange_rate`, `exchange_rate_source`, `exchange_rate_date`, `operation_key`, `igtf_status` y `exemption_reason` cuando corresponde. En la API de creación, `pagos` conserva `monto`, `codigo_metodo_pago` y `codigo_destino_pago`, con traducción de los datos fiscales nuevos. No confundir el POST web del formulario con el contrato externo de creación por API.

La creación admite `received_retentions`, `guarantee_fund`, `taxes` identificados y `fiscal_data`. Las pantallas incorporan clasificación IGTF y comprobantes recibidos; los selectores de moneda propia/cobro mixto no se implementaron en esta etapa.

## Resultado fiscal, correo y edición

El comportamiento de emisión, distribución y bloqueo por registro HKA corresponde a «Medios digitales» (`digital`), actualmente sólo DEMO. Las otras modalidades conservan su operación propia y no requieren confirmación HKA.

- Creación/detalle/listado incluyen `fiscal_emission` saneado; correo incluye `email_delivery` separado. Ninguno expone JWT, credenciales, payload completo o respuesta cruda HKA. Publicar `can_edit`/`edit_block_reason` y mantener `is_editable` coherente con la política de servidor.
- Creación/detalle/listado web/API, incluidos consumidores MobileApp, publican `pdf_downloads.a4/a5/ticket` con proveedor, URL, disponibilidad y mensaje. Aunque el ticket usa proveedor `local`, conserva los controles de descarga digital. Confirmar después del commit obtiene y guarda A4 HKA; las descargas reutilizan o recuperan copias privadas, A5 se convierte bajo demanda y 80MM continúa en memoria. No generar A4/A5 digitales locales ni modificar datos comerciales/fiscales al descargar.
- La emisión reclamada queda `pending` antes del HTTP; éxito exige respuesta de negocio e identidad/control coherentes. Rechazo definitivo es `rejected`; timeout, duplicado sin conciliar o respuesta incompleta/contradictoria es `uncertain`. Antes de transmitir se conserva `not_requested`/`prepared`, según corresponda. Consulta previa y ausencia remota acreditada condicionan un reenvío, con al menos 30 segundos desde el intento anterior.
- Un fallo fiscal posterior al commit responde venta guardada y conserva efectos comerciales. Un rollback no llama a HKA. El bloqueo común es empresa → documento → emisión y el HTTP ocurre fuera de esos bloqueos; respuestas de UUID anteriores no modifican una operación nueva.
- Editar antes del registro conserva moneda/numeración/emisor y todos los cobros. Recalcular IVA/saldo/PDF e inventario por diferencia; total no inferior a lo aplicado, mismo agente si hay retenciones e IVA suficiente. Renovar snapshot autorizado del comprador incluso al conservar cliente. Invalidar preparación editable con nuevo UUID y trazabilidad interna; guardar no envía automáticamente.
- El listado carga emisiones en lote, mantiene columna configurable «Estado HKA» y caché con namespace/versionado por tenant. Las acciones recargan el resultado. Las ventanas web/listado y POS/Garage comparten el contrato de correo y requieren assets actuales para aceptación visual.

Las reglas completas pertenecen a [emisión HKA](../../emitir-facturas-notas-hka/SKILL.md), [distribución HKA](../../distribuir-documentos-hka/SKILL.md) y [persistencia/edición](../SKILL.md). Consultar los informes enlazados por esas habilidades para evidencia real y pruebas pendientes; no convertir datos históricos en autorización de nuevos envíos.

## Verificaciones

- `tests/Unit/FiscalEmissionSchemaTest.php`: MySQL temporal, instalación/persistencia y concurrencia real con `tests/Support/fiscal_payment_concurrency_worker.php`.
- `tests/Unit/FiscalApiPaymentTransformTest.php`: compatibilidad del pago original, moneda propia, UUID y conversión con tasa explícita.
- `tests/Unit/FiscalLineCalculationTest.php`: cálculo de líneas en servidor.
- `tests/Unit/DocumentFiscalAuthorizationTest.php`: sucursal, administrador y autenticación.
- `tests/Unit/HkaPayloadBuilderTest.php` y `tests/Fixtures/Hka`: fixtures saneados y condiciones de preparación.
- `tests/Unit/HkaTransportTest.php`, `HkaResponseTest.php` y `HkaMailTest.php`: HTTP simulado, tiempos, aceptación/rechazo, identidad/control y distribución.
- `tests/Unit/DocumentEditPolicyTest.php` y `DocumentUpdateRequestTest.php`: permiso de edición y preservación del contrato comercial; complementar con persistencia/concurrencia MySQL temporal.
- `tests/js/fiscal-payment-ui.test.cjs`: clasificación y controles de cobro en fuentes.
- `tests/js/hka-mail-ui.test.cjs` y `pos-hka-email.test.cjs`: entradas compartidas, validaciones, doble clic, bloqueo y errores visibles.

Los datos ficticios `MOCK-` y los resultados históricos del informe no equivalen a pruebas de producción ni a homologación.
