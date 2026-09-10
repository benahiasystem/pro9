# Trazabilidad del contrato fiscal local

| Jira | Origen Pro6 | Contrato en Pro9 | Marcador |
| --- | --- | --- | --- |
| SCRUM-19 | `migracion_descargas_xml_cdr_comprobantes` | Ocultar descargas XML/CDR y conservar PDF | `########## INICIO CAMBIO DESCARGAS XML/CDR` / `######### FIN CAMBIO DESCARGAS XML/CDR` |
| SCRUM-22 | `migracion_eliminar_detracciones_reportes` | Eliminar el reporte exclusivo de detracciones | `########## INICIO ELIMINAR DETRACCIONES DE REPORTES` / `######### FIN ELIMINAR DETRACCIONES DE REPORTES` |
| SCRUM-41 | `migracion_ocultar_impuesto_bolsa_plastica` | Ocultar controles de impuesto a bolsas y conservar datos | `########## INICIO CAMBIO: OCULTAR IMPUESTO A LA BOLSA PLÁSTICA` / `######### FIN CAMBIO: OCULTAR IMPUESTO A LA BOLSA PLÁSTICA` |
| SCRUM-53 | `migracion_sin_detracciones_isc` | Retirar UI y presentación de ISC/detracciones sin borrar el contrato histórico | `########## INICIO SIN DETRACCIONES E ISC` / `######### FIN SIN DETRACCIONES E ISC` |
| SCRUM-54 | `migracion_sin_xml_cdr_sunat` | Registrar localmente sin XML, CDR, SUNAT, PSE ni falsa aceptación | `########## INICIO CAMBIO SIN XML CDR SUNAT` / `######### FIN CAMBIO SIN XML CDR SUNAT` |

## Puntos de control

- Política: `app/Services/LocalFiscalDocumentPolicy.php` y `config/venezuela.php`.
- Emisión: `app/CoreFacturalo/Facturalo.php`.
- Almacenamiento: `app/CoreFacturalo/Helpers/Storage/StorageDocument.php`.
- Descargas y correo: `app/Http/Controllers/Tenant/DownloadController.php` y `app/Mail/Tenant/DocumentEmail.php`.
- Rutas: `routes/web.php`, `routes/api.php`, `modules/Document/Routes/web.php`, `modules/ApiPeruDev/Routes/web.php` y `modules/Report/Routes/web.php`.
- Pruebas: `tests/Unit/LocalFiscalDocumentPolicyTest.php` y `tests/Unit/JiraInProgressMigrationContractTest.php`.

## Decisiones que no deben revertirse accidentalmente

1. `success: true` significa que el registro comercial local terminó; `sent: false` y `local: true` dejan claro que no hubo transmisión fiscal.
2. El almacenamiento debe rechazar `unsigned`, `signed`, `cdr`, `cdr_xml` y `cdr_b64` cuando la política local está activa.
3. La eliminación de una ruta fiscal es parte de la seguridad funcional, no sólo un cambio visual.
4. Se conservan los campos históricos de ISC y bolsas. La decisión posterior para Venezuela retira completamente detracciones sin históricos: esquema consolidado, datos iniciales, modelos, API, interfaz y plantillas. `payment_method_types` y las retenciones permanecen. No se añade migración incremental; `CodeErrors.xml` no se modifica.
5. El PDF es el artefacto comercial descargable y adjunto al correo.
