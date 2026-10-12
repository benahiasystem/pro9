# Contratos SPE y puntos de intervención

Rutas de código relativas a Pro9. Describir lo implementado no amplía autorización ni casos admitidos por HKA.

## Perfil validado

`SpecialTaxpayerPolicy::validate` aplica allowlist; revisar su código antes de modificar enums/reglas.

| Campos | Contrato |
|---|---|
| `rif` | `[VJEGCR]-########-#`; PN V/E/R, PJ J/C, público G/J. No verifica dígito ni inscripción SENIAT. |
| `nature`, `personal_firm`, `public_kind` | `natural/legal/public`; firma personal sólo PN y clase pública sólo naturaleza pública. |
| `public_kind` | `national`, `state`, `municipal`, `autonomous_institute`, `decentralized_non_business`, `decentralized_business`. |
| `commercial_activity`, `iva_status` | Actividad independiente; empresa emisora exige actividad. IVA `ordinary/formal/not_subject`. |
| `spe`, `notification_reference`, `notification_date` | SPE requiere notificación expresa y fecha anterior o igual a vigencia. |
| `effective_from`, `effective_until`, `revoked_at` | `Y-m-d`; fin de vigencia inclusivo, revocación SPE desde su fecha. |
| `iva_agent_basis` | `none`, `SNAT/2025/000054`, `SNAT/2013/0029`. General exige SPE y excluye PN sin firma y clases públicas específicas; pública exige naturaleza/evidencia. |
| `islr_agent`, `islr_basis` | Facultad independiente; fundamento obligatorio al habilitarla. |
| `iva_retention_rate`, `iva_100_basis` | 75/100. Causales `undisclosed_iva`, `invoice_formalities`, `portal_100`, `unregistered_rif`, `precious_metals`; 100 con evidencia. Metales no se admite como causal pública específica. |
| `iva_exclusion`, `evidence_reference` | Exclusión y soporte acreditados, sin JWT/credenciales. |

`FiscalProfileService::at`: versión con `effective_from <= fecha`, orden descendente vigencia/ID. `SpecialTaxpayerPolicy::active/spe` evalúa fin/revocación; encontrar una versión no prueba SPE vigente. Revocación SPE no borra facultad pública/ISLR independiente.

## Esquema y snapshots

- `companies.fiscal_profile`/`persons.fiscal_profile`: JSON nullable para versión actual y recursos; no mass fillable.
- `fiscal_profile_versions`: creadora `2026_08_17_000336`, ID, empresa/persona, vigencia, JSON validado, actor y fecha. El servicio escribe una entidad por fila. Índices entidad/fecha; FKs restrictivas empresa/persona/actor en `000999`, retiradas al revertir.
- `document_payments.fiscal_context`: `issuer/buyer`, `decision`, `date`; decisión conserva clasificación/intermediación/fundamento/actor.
- `document_received_retentions.fiscal_context`: perfiles, propuesta, operación, concepto ISLR congelado, control/número de factura y actor.
- `documents.issuer/customer.fiscal_profile`: versiones por fecha de emisión con `version_id`. ND limpia perfil doméstico antes de resolver identidad HKA.
- DDL sólo inicial, sin backfill/conversión de tenants existentes. Los conteos del informe son evidencia de su fecha, no constantes para futuros cambios.

## Endpoints autenticados tenant

| Endpoint | Alcance |
|---|---|
| `GET fiscal-profiles/{kind}/{id}` | `kind=company/customer`: perfil vigente, historial y `can_edit`; escritura sólo con tabla de versiones y administrador. |
| `POST fiscal-profiles/{kind}/{id}` | Nueva versión validada; cliente sólo `type=customers` y empresa con RIF propio coherente. |
| `POST fiscal-profiles/evaluate` | Cliente/documento, fecha, moneda/intermediación recibidas, importe y contexto. Publica perfiles, banderas SPE, decisión/impuesto, configuración IGTF, intermediación pendiente y propuesta IVA/ISLR. Con documento, autoriza sucursal y resuelve su cliente en servidor. |

Propuesta informativa sin efectos comerciales. Cobro/retención se revalida bajo bloqueo. `CompanyController`/`PersonController` rechazan perfil fiscal directo; Vue lo elimina del envío genérico. No sustituir historial/autoridad por un booleano comercial o mass assignment.

## Retenciones recibidas

- Entrada: IVA/ISLR, agente cliente de factura, RIF vigente exacto, número/fecha del comprobante, número visible y control real de factura, base/porcentaje/sustraendo/importe, moneda/tasa y adjunto obligatorio.
- Fecha desde emisión hasta hoy. IVA: catorce dígitos `AAAAMM########`, mes concordante, porcentaje 75/100 de propuesta y sustraendo cero. Base sobre IVA, no total de venta.
- Contexto allowlist: causal 100, evidencia, exclusión, fundamento ISLR, `public_operation_scope=subject/excluded`, `public_scope_reference`. Operación pública específica sólo elegible con ámbito sujeto documentado. Proveedor formal/no sujeto/excluido impide IVA.
- 100 exige referencia de evidencia en operación o perfil. ISLR exige concepto existente, fundamento y evidencia; rechazar `001` salarial en ventas propias. No inferir tasa/sustraendo por SPE o descripción del concepto.
- Validar fórmula/saldo/acumulados, unicidad documento/tipo/agente/número y límites: IVA sobre impuesto disponible según porcentaje; base ISLR no superior a valor neto facturado.
- Adjunto privado tenant, existente bajo `received_retentions/<document_id>/`, sin rutas ajenas/recorrido. Congelar agente/contexto y reducir saldo sin alterar venta/caja. No confundir recepción local con emisión HKA de comprobantes 05–07.

## Consumidores

| Componentes | Responsabilidad |
|---|---|
| `FiscalProfileController`, `FiscalProfileService`, `SpecialTaxpayerPolicy` | Acceso, historial, fechas, validación y propuestas. |
| `FiscalDocumentPersistence`, `Facturalo` | Snapshots, cobros/reversión, retenciones y nota derivada. |
| `HkaIgtfNote`, `HkaEmission` | Nota digital exclusiva 08/98, cobro vigente, padre confirmado/coherente, envío fuera de transacción y recuperación. |
| `HkaPayloadBuilder`, `HkaDomesticMoney` | RIF/`infoAdicional`, copia VES/OtraMoneda USD y referencia afectada con moneda/tasa original. |
| `fiscal_profiles/editor.vue` | Perfil separado en empresa/cliente, historial y guardado administrativo. |
| `documents/partials/igtf_fields.vue`, `retention.vue` | Conversión/propuesta compartida y datos/soporte de comprobante recibido. |
| `pos/partials/payment.vue`, `fast_payment.vue`, `fast_payment_garage.vue` | Crédito, importes reales, almacenamiento y recuperación del borrador. |
| `multiple_payment.vue`, `multiple_payment_garage.vue` | `paymentConditionId`; no proponer/distribuir total en crédito; cancelar conserva snapshot. |
| `Template`, `pdf/partials/fiscal_profiles.blade.php`, `SpecialTaxpayerPolicy::forPrint` | RIF/SPE desde snapshots en representación local; ticket reserva 12 mm por perfil. |

No extender la nota exclusiva a exportación, terceros o regímenes bloqueados por sus políticas. La API conserva campos de cobro compartidos, pero la matriz de navegador/aceptación acreditada es Web/POS/Garage; no certificar otros canales por compatibilidad de campos.
