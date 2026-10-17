# Contratos IVA recibida y puntos de intervención

Rutas relativas a Pro9. Leer el código vigente antes de modificar campos/estados. El [informe de implementación](../../../../informes/retenciones_iva_recibidas_hka.md) conserva ejemplos API y matriz por módulo; esta referencia delimita responsabilidades y recuperación.

## Servicios y presentación

| Archivo o componente | Responsabilidad |
|---|---|
| `app/Services/Fiscal/IvaRetentionService.php` | Propuesta/previsión, registro idempotente, soporte, validación/activación, reversión, crédito y DTO público. |
| `app/Services/Fiscal/FiscalDocumentPersistence.php` | Delega IVA e ISLR a políticas separadas con ciclo ReceivedRetentionService. |
| `app/CoreFacturalo/Facturalo.php` | Creación común, edición/previsión recalculada y transacción exterior. |
| `app/Services/Fiscal/HkaIvaRetention.php` / `HkaReceivedRetention` | Adaptador IVA separado y ciclo común de outbox/reclamo/conciliación. |
| `app/Services/Fiscal/HkaIvaRetentionPayload.php` | Identidad congelada tipo 01, dinero del comprobante y comparación exacta. |
| `HkaIvaRetentionCapability` / `config/iva_retention.php` | Bloqueo del neto no aceptado por HKA. |
| `HkaEmission` | Tras confirmar factura, reanuda comprobantes pendientes fuera de la transacción. |
| `HkaEmissionPreparation`, `HkaPayloadBuilder`, `DocumentEmissionView` | Snapshot con previsión/comprobantes, guardia y diagnóstico/acciones coherentes. |
| `IvaRetentionController` | Propuesta, upload temporal, descarga privada, consulta/reintento/reversión. |
| `DocumentFiscalController` | Consulta/registro/carga vinculada conservados; autorización tenant/sucursal. |
| `DocumentResource`, `DocumentCollection`, `DocumentPaymentController`, `Document::getApiResourceFind` | Resumen de creación/detalle/listado/cobros; MobileApp reutiliza recursos comunes. |
| `DocumentEditSettlements` | Sólo aplicaciones vigentes condicionan cliente/IVA; límites fiscales VES. |
| `CashController` / `CashDocumentCredit` | Estado del crédito por saldo, sin representar retención como efectivo. |
| `RecoverIvaRetentions` / `Console/Kernel` | Pendientes con factura lista, operaciones debidas y limpieza; scheduler tenant por minuto sin superposición. |
| `documents/partials/retention.vue` | Historial/soporte/formulario sobre factura guardada desde el listado; errores Web/API legibles y protección de respuestas tardías. |
| `resources/js/helpers/document-creation.js` / `mixins/document-creation.js` | Excluir retenciones de creación/borradores; objetivo liquidable y sugerencia exacta del restante, sin redistribuir abonos. |
| `resources/views/pdf/partials/document_fiscal_totals.blade.php` / `Template` | Aplicaciones/previsión locales y altura del ticket. |

## Entradas de interfaz — contrato del 16/10/2026

- El listado `/documents` conserva la acción Retención con gestión IVA/ISLR: registro, soporte privado vinculado, consulta, reintento y reversión. No requiere recrear la factura.
- Facturas, POS/Garage (incluidos pagos rápidos), Hotel, pedidos, servicio técnico y ambas suscripciones no presentan previsión/carga. Sus ventanas de confirmación y pagos compartidos tampoco montan el gestor.
- Sólo el listado pasa `allowRetentionManagement=true` a las opciones de documento y de allí a operaciones de NC. Fuera de ese contexto no se ofrecen ajustes/reemplazos IVA/ISLR; las demás operaciones de devolución continúan.
- `withoutDraftRetentions` excluye `iva_retention_prevision`, `islr_retention_prevision` y `received_retentions` al restaurar/guardar borradores POS/Garage y preparar solicitudes/preview. Conservar artículos y cobros. `collectionTarget` no deduce previsiones; `suggestRemainingPayment` conserva filas ingresadas y sugiere únicamente el restante en moneda documental.
- Los componentes `received_retention_options.vue`, `iva_retention_fields.vue`, `islr_retention_fields.vue` y el mixin `iva-retention.js` se retiraron al quedar sin consumidores. No reintroducirlos en creación por interpretar capacidades históricas del API como requisito de interfaz.
- `DocumentInput`, `DocumentUpdateInput`, `DocumentTransform` y los servicios/API conservan sus contratos y las retenciones persistidas. No modificar tablas ni históricos para esta retirada UI.

## Esquema inicial y soporte

| Creadora | Contrato |
|---|---|
| `000332 document_received_retentions` | Estado, UUID registro/reversión, hash/solicitud privados, `taxable_base`, diagnóstico, actor/motivo/fecha. Índices generados de IVA activo y número de comprobante activo. |
| `000334 document_fiscal_data` | Previsión JSON nullable fuera de pagos/aplicaciones. |
| `000337 document_iva_retention_operations` | Factura/comprobante, UUID, acción única apply/remove, payload/contexto congelados, intento, contadores de mutaciones/fallos, estado, próximo intento, actor/fechas y diagnóstico. |
| `000338 received_retention_uploads` | Token único, usuario, ruta privada, vencimiento y documento consumidor. |
| `000999` | FKs factura/comprobante/actor/upload/reversión; retirada inversa antes de tablas. |

Importes `decimal(12,2)`; tasas `decimal(18,8)`. Los índices permiten históricos con el mismo número, sólo uno activo. UUID de registro/reversión conserva unicidad y validación bajo bloqueo para impedir reutilización entre acciones. Evidencia inicial IVA: 342 tablas/343 archivos; ISLR agregó 000339 y el inventario actual es 343 tablas de aplicación y 344 archivos tenant; Laravel administra `migrations`.

Temporal: `received_retentions/drafts/<uuid>.<ext>`, token por usuario/tenant de 24 horas y consumo único. Soporte vinculado: `received_retentions/<document_id>/`. Copiar preserva temporal ante rollback. Consumo/limpieza toman el mismo bloqueo; vencer un token consumido no elimina su soporte. Copias huérfanas propias se identifican por nombre de ese token y ausencia de referencia persistida.

## API y estados

Web conserva sesión/CSRF; API añade `/api` y autenticación tenant. Para campos exactos/ejemplos leer el informe.

| Operación | Ruta |
|---|---|
| Propuesta | `POST documents/retention/proposal` |
| Soporte previo a crear | `POST documents/retention/upload-draft` |
| Soporte vinculado | `POST documents/retention/upload` |
| Consulta local | `GET documents/retention/{document}` |
| Registro | `POST documents/retention` |
| Descarga privada | `GET documents/retention/{id}/attachment` |
| Consulta HKA | `POST documents/retention/{id}/query-hka` |
| Reintento | `POST documents/retention/{id}/retry-hka` |
| Reversión | `POST documents/retention/{id}/reverse` |

Contrato API de creación (no usado por la interfaz): `iva_retention_prevision={enabled,fiscal_context}` y `received_retentions[]` con token `upload_id`. El servidor recalcula importes; no confiar en `amount` previsto publicado por cliente. Consulta no exige nuevo UUID; registro/reversión lo exigen y retry usa la clave HKA vigente. Reutilizar clave con otros datos/acción: 409; validación ordinaria: 422. Handler y Vue conservan/interpretan los sobres Web/API.

| Estado local | Efecto en saldo |
|---|---|
| Previsión en datos fiscales | Ninguno. |
| `pending_validation` | Cero, espera control/confirmación y cotejo. |
| `invalid` | Cero, conserva soporte/input y diagnóstico para reemplazo. |
| `applied` | `applied_amount` documental, válido aun si HKA falla. |
| `reversed` | Cero, conserva importe anterior en auditoría y original del comprobante. |

`reversal_pending` conserva `applied` y efecto financiero. HKA informa separado `waiting_invoice`, `waiting`, `pending`, `confirmed`, `uncertain`, `rejected`, `conflict`, `removed`, `cancelled` o `not_applicable`, según operación/modalidad; no reutilizar estado de factura.

## Lectura remota y recuperación

GET/DELETE: `serie`, `tipoDocumento=01`, `numeroDocumento`, `numeroControl` en JSON. Serie/número/moneda vienen del payload confirmado y control real; `number_full` es referencia visible, no identidad HKA. POST agrega `totalRetencion`: comprobante/fecha, base imponible, IVA e importe retenido.

Sobre de ausencia verificado el 12/10/2026: HTTP 200 con:

```json
{
  "codigo": "203",
  "mensaje": "Retención no encontrada",
  "validaciones": ["No se ha aplicado retención previamente por lo que no se puede consultar nada"]
}
```

Comparar sobre específico, no sólo código. Resultado 200 sin validaciones explícitamente vacío también acredita ausencia; totales nulos sólo tras cotejar identidad completa. Campo ausente no equivale a nulo. Otro comprobante es conflicto aun sin otros totales; coincidencia exige identidad, comprobante, fecha e importes completos/exactos.

`process` autentica fuera de bloqueos, reclama empresa/factura/comprobante/operación y libera antes de HTTP. Leer antes de cada mutación y después; ante incertidumbre, consultar antes de otro POST/DELETE. Finalizar sólo para UUID/contexto vigente. Contexto congela emisor/modalidad/ambiente y huella de credenciales cifradas, sin claves/JWT.

`attempts` cuenta mutaciones HTTP; `failures` gobierna espera progresiva de 60 segundos hasta una hora. Mantener gracia de 90 segundos de operación en curso y eliminación frente a aplicación incierta. Una coincidencia remota adoptada puede tener cero POST locales y aun necesitar eliminación al revertir. Rechazo definitivo/conflicto no se retransmite automáticamente.

Consulta manual de conflicto corregido: aplicación ausente conserva conflicto, coincidencia confirma; reversión ausente permite cerrar. Eliminar confirmado cancela intentos anteriores y restituye saldo una vez. Respuesta tardía de intento cancelado no revive el comprobante.

## Evidencia y límites

Ver las tres evidencias del informe: rechazo del neto Contado, rechazo del neto Crédito y control POST/GET/DELETE VES/USD. Control completamente cobrado no acredita neto. La bandera DEMO permanece falsa fuera de dobles de prueba. Producción, emisión 05 y aceptación visual integral en instalación nueva no se acreditaron.

Pruebas mantenidas: `IvaRetentionPersistenceCases` en `FiscalEmissionSchemaTest`, `HkaIvaRetentionPayloadTest`, `IvaRetentionHttpContractTest`, `HkaTransportTest`, `HkaPayloadBuilderTest`, `tests/js/iva-retention-ui.test.cjs` y `tests/js/received-retention-list.test.cjs`. Worker fiscal sólo con bases temporales de nombre controlado; sus dobles no autorizan mutaciones reales.
