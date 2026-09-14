---
name: mantener-operacion-local-fiscal-pro9
description: Mantener la operación comercial local de Pro9 para instalaciones nuevas, sin XML/CDR/SUNAT/PSE ni campos de ISC, detracciones o impuesto a bolsas, conservando PDF y funciones comerciales vigentes. Usar al tocar Facturalo, documentos, guías, correo, descargas, items, configuración, reportes, PDF o rutas fiscales.
---

# Mantener operación local fiscal de Pro9

## Evolución aprobada

Para la implementación de numeración y emisión de SCRUM-39, aplicar [el contrato de numeración fiscal](../mantener-numeracion-fiscal-venezuela-pro9/SKILL.md). Sustituye la limitación de emisión exclusivamente local por estados separados de registro comercial y resultado fiscal. No restaura XML/CDR/SUNAT/PSE. Los adaptadores simulados sólo acreditan pruebas demo; consultar el informe de numeración antes de afirmar que un flujo está integrado.

## Objetivo

Sostener conjuntamente los contratos de SCRUM-19, SCRUM-22, SCRUM-41, SCRUM-53 y SCRUM-54. Antes de cambiarlos, leer [references/traceability.md](references/traceability.md).

## Política central

- Para modalidad, ambiente y retirada total de SOAP/PFX, aplicar [mantener-modalidad-emision-fiscal-pro9](../mantener-modalidad-emision-fiscal-pro9/SKILL.md). Se trabaja con instalación nueva y esquema consolidado, sin conversión de datos anteriores. `LocalFiscalDocumentPolicy::enabled()` es permanente: cambiar una variable de entorno no puede reactivar la transmisión peruana. Los certificados QZ Tray permanecen fuera de esa retirada.

- Usar `App\Services\LocalFiscalDocumentPolicy` como fuente de la respuesta de emisión local. ISC no tiene campos ni interruptor de visibilidad.
- Registrar los documentos con estado local `REGISTERED` y respuesta `LOCAL_REGISTERED`.
- Nunca representar un registro local como enviado, aceptado por SUNAT/SENIAT/PSE ni acompañado de XML, hash o CDR.
- Eliminar productores, firmadores, lectores, respuestas y plantillas XML/CDR. Mantener barreras explícitas en `StorageDocument` y `DownloadController` para rechazar nombres de archivo o tipos fiscales antiguos, y conservar generación, descarga, impresión y correo del PDF.
- Desregistrar las rutas de envío, validación, consulta de CDR/ticket y regularización fiscal. No basta con ocultar botones si la ruta sigue activa.

## Detracciones retiradas

- El sistema venezolano no usa detracciones y no existen históricos que conservar: retirar el módulo completo, sus campos, constancias y `cat_payment_method_types`. No confundir con `payment_method_types`, que permanece operativo.
- Ignorar los campos antiguos de detracción recibidos por API, sin validarlos, persistirlos ni devolverlos. Conservar retenciones, fondos de garantía y pagos comerciales.
- Modificar migraciones consolidadas y `tenant_initial_data.php`; no crear migraciones incrementales ni ejecutar reconstrucciones sobre tenants reales.

## Instalación sin compatibilidad histórica

- Retirar columnas, modelos, relaciones, casts, API y cálculos de ISC e impuesto a bolsas. No conservar ceros, flags de visibilidad ni catálogos opcionales como sustitución de la retirada.
- Eliminar `SystemIscType` y sus consumidores. Actualizar las creaciones base y las semillas directamente; no convertir tenants anteriores.
- Quitar controles y filas de reportes/PDF, manteniendo encabezados y totales alineados. Conservar IVA, descuentos, cargos, pagos e inventario actuales.
- Retirar resúmenes fiscales de Boletas, sus rutas/tablas/componentes y los campos de envío individual. Mantener anulaciones locales y los resúmenes comerciales de caja; no son el mismo módulo.
- Verificar la eliminación de documentos demo con `TestDocumentsDeletionBehaviorTest` sobre una base en memoria sin `summary_documents`: debe limpiar las dependencias comerciales vigentes sin consultar tablas fiscales retiradas.
- Retirar plantillas XML y el árbol `app/CoreFacturalo/WS`, incluido su catálogo `CodeErrors.xml`, porque ya no existen productores ni consumidores de ese transporte. Conservar QZ para impresión.
- `ActionInput` y `ActionTransform` sólo preparan correo, formato PDF e impresión automática. No exponer `send_xml_signed`/`enviar_xml_firmado` ni consultar configuración para decidir envíos retirados. Probar las acciones comerciales y sus valores predeterminados sin base de datos.
- Retirar `SummaryResult`, sin consumidores tras eliminar los resúmenes fiscales de Boletas.
- Retirar el módulo `PseService` completo y deshabilitar su entrada en `modules_statuses.json`; no dejar proveedores, endpoints, rutas de demostración ni seeders PSE.
- Retirar el módulo `Sire`, su interfaz, menú, rutas y credenciales de empresa (`sire_client_id`, `sire_client_secret`, `sire_username`, `sire_password`); corresponde al registro peruano de compras y ventas y no tiene función local venezolana.
- Retirar la migración de notas de venta hacia otro servidor: modelos `MigrationConfiguration`/`SaleNoteMigration`, rutas `UpToOther`, configuración, botones y componentes. Es una herramienta de traslado de datos anteriores, no una función de una instalación nueva.
- Conservar el módulo de facturación masiva, sus rutas de carga/proceso/listado/exportación y su descarga PDF. Sustituir `estado_sunat`/`mensaje_sunat` por `estado_emision`/`mensaje_emision`; no crear enlaces XML/CDR ni estados de aceptación externa. La respuesta HTTP correcta se presenta como `Registrado localmente`.
- Consolidar `massive_invoices` en su migración creadora. No agregar migraciones incrementales para instalaciones o registros anteriores y no conservar la ruta `massive-invoice/config` si el controlador no implementa esa acción.
- Retirar de `configurations` los selectores `send_auto`, `sunat_alternate_server`, `auto_send_dispatchs_to_sunat` y `send_data_to_other_server`. La interfaz y las respuestas no deben publicar controles inertes de transporte.
- Retirar comandos de consulta, validación, reenvío y regularización masiva. Los controladores de documentos, anulaciones, percepciones, retenciones, liquidaciones y órdenes de entrega sólo guardan el registro local y generan PDF cuando corresponde.
- Mantener series, correlativos, items, inventario, pagos, notas, PDF y correo comercial.
- Conservar `OfflineTrait` en controladores que todavía llaman `getIsClient()`. Retirar métodos de envío entre servidores no autoriza quitar esa dependencia: `documents/index`, `documents/tables` y `documents/item/tables` publican `is_client` para el modo offline vigente.
- El bot consulta `local_state`: registrado localmente, anulado o por anular. No ofrecer estados de aceptación SUNAT ni campos XML/CDR en `QueryDocumentStatusTool`. Su prompt y definición describen operación local; las pruebas no deben enviar mensajes reales.

## Cambios de interfaz y reportes

- En listas de documentos, percepciones, retenciones, liquidaciones y contingencias mostrar PDF, pero no XML/CDR ni reenvío fiscal.
- El tablero no registra rutas, fuentes, componentes ni widgets de estado SUNAT. Los webhooks sólo publican eventos del ciclo local: creación, anulación y compras; no aceptación, observación o rechazo de una autoridad externa.
- Al retirar ayudas fiscales obsoletas, eliminar también sus imports, propiedades y computed de los componentes globales. `GlobalHelpButton.vue` no debe importar el eliminado `help_summaries.json`; conserva tours y apertura del centro de ayuda.
- Eliminar completamente el reporte exclusivo de detracciones: menú, rutas, controlador, recurso, vista y componente.
- En formularios de items, documentos y configuración retirar controles de ISC y detracción.
- Los reportes y PDF no contienen campos ni llamadas a `showIsc()`.
- Los PDF fiscales no muestran QR ni hash derivados de XML. Se conservan los QR propios de Órdenes de entrega y formularios cuando representan una URL comercial vigente.
- Retirar completamente la opción peruana de Amazonía: `companies.operation_amazonia`, `configurations.legend_footer`, `configurations.legend_forest_to_xml`, la plantilla `legend_amazonia`, los parciales `footer_legend` y las ramas por departamento 16. Conservar `legend_footer_sale`, que es el texto libre vigente del pie de ventas.
- Retirar `configurations.name_product_pdf_to_xml` y `document_items.name_product_xml`; `name_product_pdf` permanece como descripción comercial para impresión.
- Retirar también etiquetas de validación y comentarios de campos ISC eliminados; no dejar contratos de presentación que sugieran su configuración. `CurrentPdfTemplateContractTest` compila en memoria todas las plantillas PDF y analiza el PHP generado, sin escribir assets ni acreditar por sí solo la presentación visual.
- Revisar también variables PHP de los parciales de reportes generales de artículos: no leer relaciones ISC aunque la columna visible ya se haya retirado. Conservar cálculos de IVA, descuentos, utilidad y conversión vigente de USD a bolívares.
- El estado y los payloads no contienen campos de impuesto a bolsas, incluidos totales auxiliares.
- En Hotel, el modal **Agregar Producto o Servicio** no muestra la sección **Agregar Descuentos/Cargos/Atributos especiales**. Mantener la regla vigente del flujo y los descuentos/cargos comerciales que utilice, sin conservar datos exclusivamente históricos.

## Marcadores y verificación

- Delimitar cambios con los marcadores exactos documentados en la referencia.
- Ejecutar `tests/Unit/LocalFiscalDocumentPolicyTest.php` y `tests/Unit/JiraInProgressMigrationContractTest.php` junto con las pruebas de contratos Venezuela afectadas.
- Ejecutar validación de sintaxis PHP y `git diff --check`.
- Si se modifican Vue o JavaScript, aplicar `frontend-build` antes de compilar. No ejecutar una compilación por iniciativa propia cuando ese skill la prohíba; informar que el bundle queda pendiente.
- `CurrentPdfRenderingTest` renderiza la plantilla real `default/invoice_a4` con mPDF, verifica el contrato local y permite exportar una muestra mediante `PRO9_PDF_FIXTURE_PATH`. Complementar con `pdfinfo`, extracción de texto y render PNG; revisar visualmente la página generada.
- Analizar las fuentes Vue modificadas con `vue-template-compiler` y `@babel/parser` cuando no se haya autorizado compilar. Esta comprobación detecta errores de plantilla/script, pero no reemplaza el bundle de producción.

## Resolución de anulaciones locales

- `Functions::voidedDocuments` no recibe un selector de resumen fiscal: su único consumidor es `VoidedValidation` y consulta directamente el grupo 01, por código externo y fecha. No reintroducir una alternativa de grupo 02 para Boletas.
- `CurrentLocalDocumentReferenceTest` ejecuta consultas Eloquent en SQLite en memoria: conserva la referencia de Factura para notas, rechaza documentos no admitidos o inexistentes y verifica grupo/fecha de anulaciones. No acredita la persistencia completa ni efectos de una anulación.
