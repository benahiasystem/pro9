# Puntos de intervención ISLR

| Archivo | Responsabilidad |
|---|---|
| `IslrRetentionService` | Conceptos, propuesta declarada, perfiles, base/fórmula y previsión. |
| `ReceivedRetentionService` | Ciclo transaccional común IVA/ISLR; soporte, hash/UUID, activación, reversión y DTO público. |
| `FiscalDocumentPersistence`, `Facturalo` | Todos los consumidores comerciales, edición/previsión y commit exterior. |
| `HkaReceivedRetention` | Outbox/reclamo/conciliación y recuperación común. |
| `HkaIslrRetention`, `HkaIslrRetentionPayload` | Contrato/ausencia ISLR y campos remotamente verificables. |
| `HkaIslrRetentionCapability`, `config/islr_retention.php` | Bloqueo del neto sin aceptación. |
| `HkaEmission`, `HkaPayloadBuilder`, `DocumentEmissionView` | Activación después de confirmar, guardia/diagnóstico de factura. |
| `IvaRetentionController` | Rutas conservadas; propuesta por tax_kind y acciones desde registro autorizado. |
| `DocumentFiscalController` | Consulta/registro/carga y catálogos; sucursal y permisos. |
| `DocumentResource`, `DocumentCollection`, `DocumentPaymentController`, API/Web create y `Document::getApiResourceFind` | Publican IVA e ISLR; MobileApp consume recursos comunes. |
| `DocumentInput`, `DocumentUpdateInput`, `DocumentTransform` | Preservan previsión/comprobantes en los contratos compartidos. |
| `retention.vue`, `helpers/document-creation.js`, mixin `document-creation.js` | Gestión desde el listado sobre factura guardada; creación sin datos de retención y conservación de abonos. |
| `RecoverIslrRetentions`, `Console/Kernel` | Reanuda pendientes por tipo y operaciones debidas cada minuto. |
| `DocumentEditSettlements` | Conserva cliente, neto respaldado y aplicaciones vigentes. |

DDL consolidado: `000332` índice generado `active_islr_document_id` nullable, único para pending_validation/applied; `000334` JSON previsión; `000339` outbox independiente con resultado saneado privado; `000999` FKs factura/comprobante/actor, rollback inverso. Mantener `000337` IVA y `000338` uploads sin renombrar. Inventario actual 343 tablas de aplicación, 344 creadoras/FKs tenant, más tabla Laravel `migrations` cuando el migrador se usa.

API reutiliza `/documents/retention` (prefijo `/api` con token). Propuesta incluye `tax_kind=ISLR`, catálogo no salarial mediante GET `/documents/retention/concepts`. Registro/carga: create_payment; reversión/reintento de eliminación: delete_payment/admin. Descarga/consulta autoriza factura/sucursal. UUID del retry es el de la operación HKA vigente. Respuestas no devuelven payload/contexto/outbox privado.

IVA e ISLR permanecen separados al revertir, consultar HKA y cargar comprobantes. Cambiar tipos, autorizar desde un identificador ajeno o reconstruir identidad desde datos recibidos rompe el contrato.

## Interfaz centralizada — 16/10/2026

Gestionar IVA/ISLR sólo desde el listado `/documents`; no ofrecer previsión/carga durante creación, pago ni confirmación en los módulos comerciales. Excluir retenciones de borradores/solicitudes UI, conservar cobros y usar el total liquidable sin deducciones antiguas. Los ajustes/reemplazos de NC requieren `allowRetentionManagement`, falso por defecto y habilitado por el listado. Mantener APIs, servicios, datos y soportes históricos. Verificar con `tests/js/received-retention-list.test.cjs` y regresiones de pagos/retenciones.
