# Trazabilidad del contrato fiscal local

| Jira | Origen Pro6 | Contrato en Pro9 | Marcador |
| --- | --- | --- | --- |
| SCRUM-19 | `migracion_descargas_xml_cdr_comprobantes` | Ocultar descargas XML/CDR y conservar PDF | `########## INICIO CAMBIO DESCARGAS XML/CDR` / `######### FIN CAMBIO DESCARGAS XML/CDR` |
| SCRUM-22 | `migracion_eliminar_detracciones_reportes` | Eliminar el reporte exclusivo de detracciones | `########## INICIO ELIMINAR DETRACCIONES DE REPORTES` / `######### FIN ELIMINAR DETRACCIONES DE REPORTES` |
| SCRUM-41 | `migracion_ocultar_impuesto_bolsa_plastica` | Retirada completa del impuesto a bolsas para instalación nueva | `########## INICIO CAMBIO: OCULTAR IMPUESTO A LA BOLSA PLÁSTICA` / `######### FIN CAMBIO: OCULTAR IMPUESTO A LA BOLSA PLÁSTICA` |
| SCRUM-53 | `migracion_sin_detracciones_isc` | Retirada completa de ISC/detracciones para instalación nueva | `########## INICIO SIN DETRACCIONES E ISC` / `######### FIN SIN DETRACCIONES E ISC` |
| SCRUM-54 | `migracion_sin_xml_cdr_sunat` | Registrar localmente sin XML, CDR, SUNAT, PSE ni falsa aceptación | `########## INICIO CAMBIO SIN XML CDR SUNAT` / `######### FIN CAMBIO SIN XML CDR SUNAT` |

## Puntos de control

- Política: `app/Services/LocalFiscalDocumentPolicy.php` y `config/venezuela.php`.
- Emisión: `app/CoreFacturalo/Facturalo.php`.
- Almacenamiento: `app/CoreFacturalo/Helpers/Storage/StorageDocument.php`.
- Descargas y correo: `app/Http/Controllers/Tenant/DownloadController.php` y `app/Mail/Tenant/DocumentEmail.php`.
- Rutas: `routes/web.php`, `routes/api.php`, `modules/Document/Routes/web.php`, `modules/ApiPeruDev/Routes/web.php` y `modules/Report/Routes/web.php`.
- Pruebas: `tests/Unit/LocalFiscalDocumentPolicyTest.php` y `tests/Unit/JiraInProgressMigrationContractTest.php`.

## Decisiones que no deben revertirse accidentalmente

1. `success: true`, `local: true` y `code: LOCAL_REGISTERED` significan que el registro comercial local terminó. La respuesta no incluye `sent`, `xml_signed`, `hash` ni otros campos de transporte.
2. El almacenamiento debe rechazar `unsigned`, `signed`, `cdr`, `cdr_xml` y `cdr_b64` cuando la política local está activa.
3. La eliminación de una ruta fiscal es parte de la seguridad funcional, no sólo un cambio visual.
4. La decisión de instalación nueva sustituye la conservación de campos históricos de ISC y bolsas: retirar esos campos y detracciones del esquema consolidado, datos iniciales, modelos, API, interfaz y plantillas. `payment_method_types` y las retenciones permanecen. No se añade migración incremental.
5. El PDF es el artefacto comercial descargable y adjunto al correo.

## Retirada total de SOAP/PFX — 10 de septiembre de 2026

La decisión de modalidad fiscal sustituye la conservación histórica de SOAP/PFX. `LocalFiscalDocumentPolicy::enabled()` es permanente. Se retiran controladores y componentes de certificados fiscales, clientes WS, firmadores, plantillas XML y el módulo PSE. QZ Tray conserva su función de impresión.

`DocumentEmail` sólo lee y adjunta PDF. Los webhooks documentales presentan `local_response`, `fiscal_environment` y `fiscal_emission_mode`, sin enlaces XML/CDR. `Facturalo` no contiene métodos de creación, firma, envío, consulta o regularización XML/CDR.

Los contratos de transporte retirados abarcan columnas de `documents`, `dispatches`, `perceptions`, `retentions`, `purchase_settlements` y `voided`; comandos masivos; rutas API/web; recursos; botones; el widget SUNAT; eventos de webhook externos; el módulo PSE y la migración de notas hacia otro servidor. `FiscalEmissionSchemaTest` comprueba estas ausencias en una instalación temporal, con rollback y repetición.

La facturación masiva permanece como función vigente. `massive_invoices` almacena `estado_emision` y `mensaje_emision`; el controlador registra `Registrado localmente` o `Error`, la vista y el Excel leen ese estado y la única descarga admitida es PDF. El esquema no crea `estado_sunat`, `mensaje_sunat`, `xml_link` ni `cdr_link`. `LocalFiscalDocumentPolicyTest` protege también la existencia de las rutas funcionales del módulo.

La configuración se rige por [mantener-modalidad-emision-fiscal-pro9](../../mantener-modalidad-emision-fiscal-pro9/SKILL.md). Sólo hay inicialización nueva: campos obligatorios y auditoría directamente en el consolidado, sin migración de conversión ni validadores de payloads SOAP/PFX antiguos. Pruebas adicionales: `FiscalEmissionSettingsTest`, `FiscalEmissionSchemaTest` y comportamiento de correo/política permanente en `LocalFiscalDocumentPolicyTest`. No interpretar el ambiente Producción como autorización o integración fiscal efectiva.
