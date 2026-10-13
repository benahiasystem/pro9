# Retenciones ISLR recibidas en Pro9 — 12/10/2026

Implementación para instalaciones nuevas: previsión, registro durante creación y posterior, aplicación local, soporte privado, historial, HKA y reversión. Pertenece a la factura fiscal resultante; las conversiones no trasladan comprobantes de otras facturas. Proveedores HKA 06, salarios, ARC, XML SENIAT, producción y conversión de tenants existentes quedan fuera.

Contrato contrastado con el [Swagger oficial HKA](https://demoemisionv2.thefactoryhka.com.ve/swagger/v1/swagger.json), SHA256 `f2ddb07c239b04319df1fc9b1e6b4db137f89b51f063fa4989fd1bc836d10f04`. El PDF técnico recibido explica retenciones IVA; los DTO ISLR recibidos se contrastaron con Swagger, sin confundirlos con emisión tipo 06 del portal. Las etiquetas históricas de conceptos no autorizan tasas automáticas.

## Comportamiento y arquitectura

`IslrRetentionService` conserva reglas declaradas y perfiles versionados. `ReceivedRetentionService` comparte con IVA transacción, soporte, UUID/hash, permisos, saldo/crédito, activación/reversión y resumen público. `HkaReceivedRetention` comparte reclamo/recuperación, con adaptadores, parsers y outboxes separados.

Un comprobante activo por tipo y factura, concepto único no salarial. Fórmula `max(0, base × porcentaje / 100 − sustraendo)`, importes positivos y límites de base fiscal neta/saldo acumulado. Concepto 001 excluido. Número texto hasta 50 caracteres. Moneda VES/USD y tasa ocho decimales exactos. RIF/facultad ISLR por fecha; SPE no determina concepto/porcentaje.

La previsión no reduce saldo: total 116, IVA prevista 12, ISLR prevista 3 y efectivo 101 deja saldo 15. Aplicar IVA deja 3; aplicar ISLR deja cero. Caja conserva 101. Reversión no crea efectivo ni altera total/IVA causado/originales HKA.

Pendiente de validar → aplicada o inválida al confirmar número/control real. Invalidar conserva soporte y solicitud privada, aplicada cero. Estados HKA independientes. HTTP se ejecuta después del commit exterior; savepoint evita aplicación parcial si falla la preparación. Timeout → consulta antes de retransmitir; otro comprobante → conflicto sin sobrescritura. Reversión pendiente conserva saldo aplicado hasta acreditar ausencia remota. Operación nunca transmitida permite reversión local; adopción remota con cero intentos requiere eliminación conciliada.

DDL inicial: generado único ISLR en 000332, JSON `islr_retention_prevision` en 000334, outbox `document_islr_retention_operations` en 000339 y FKs en 000999. 343 tablas de aplicación/344 archivos tenant. No se migraron bases reales.

## API

Rutas bajo sesión Web o `/api` autenticado tenant. Respuestas de creación/detalle/listado/cobros añaden `islr_retention` junto a IVA: previsión, aplicado, balance, registros, estado HKA, diagnóstico y acciones autorizadas. Consulta remota ISLR sólo acredita `verified_fields`: identidad, comprobante, fecha e impuesto; no acredita concepto/RIF/porcentaje/sustraendo.

| Método y ruta | Uso |
|---|---|
| GET `/documents/retention/concepts` | Catálogo no salarial con id/description, sin tasas automáticas. |
| POST `/documents/retention/proposal` | `tax_kind=ISLR`, datos declarados, cliente/factura y contexto. Omitir tax_kind conserva IVA. |
| POST `/documents/retention/upload-draft` | Multipart file, token de 24h por tenant/usuario. |
| POST `/documents/retention/upload` | Multipart document_id/file, soporte privado vinculado. |
| GET `/documents/retention/{document}` | Historial local y resumen de ambos impuestos. |
| POST `/documents/retention` | Registro idempotente con UUID. |
| GET `/documents/retention/{id}/attachment` | Descarga privada autenticada. |
| POST `/documents/retention/{id}/query-hka` | Tipo resuelto desde comprobante autorizado. |
| POST `/documents/retention/{id}/retry-hka` | UUID de operación HKA vigente. |
| POST `/documents/retention/{id}/reverse` | UUID nuevo y motivo, reversión auditada. |

Registrar/cargar exige `create_payment`/admin. Revertir/reintentar eliminación exige `delete_payment`/admin. Tenant y sucursal se resuelven en servidor. UUID idéntico/datos idénticos devuelve resultado; reutilización incompatible HTTP 409, validación ordinaria 422. Soporte PDF/JPG/PNG máximo 5 MB. Limpieza de temporales bajo el mismo bloqueo que consumo.

Ejemplo de propuesta para creación (moneda documental separada de la del comprobante):

```json
{
  "tax_kind": "ISLR", "customer_id": 123, "date": "2026-10-12",
  "total_value": 100, "total_taxed": 100, "total_igv": 16,
  "document_currency_type_id": "VES", "exchange_rate_sale": "1.00000000",
  "currency_type_id": "VES", "exchange_rate": "1.00000000",
  "concept_id": "055", "base_mode": "declared", "base": 100,
  "percentage": 4, "subtrahend": 1,
  "fiscal_context": {"concept_basis": "Fundamento acreditado por la operación", "evidence_reference": "Expediente privado del agente"}
}
```

Con `document_id`, servidor resuelve cliente, neto, moneda y tasa fiscal. `base_mode=full_net` inicia desde neto fiscal; `declared` conserva base acreditada. Propuesta publica amount en moneda documental y voucher_amount en moneda del comprobante.

Ejemplo de registro posterior:

```json
{
  "document_id": 456, "tax_kind": "ISLR",
  "operation_key": "752596b3-fc8f-412d-b98c-cd662eb94d16",
  "voucher_number": "ISLR-CLIENTE-2026-001", "voucher_date": "2026-10-12",
  "agent_rif": "J-12345678-9", "invoice_number": "F01-00000456", "invoice_control": "00-00000456",
  "concept_id": "055", "base": 100, "percentage": 4, "subtrahend": 1, "amount": 3,
  "currency_type_id": "VES", "exchange_rate": "1.00000000",
  "attachment": "received_retentions/456/archivo-privado.pdf",
  "fiscal_context": {"concept_basis": "Fundamento acreditado", "evidence_reference": "Soporte recibido"}
}
```

En creación: añadir `islr_retention_prevision` con `enabled=true` y los campos de concepto/base_mode/moneda/tasa/porcentaje/sustraendo/contexto de la propuesta; amount se recalcula. `received_retentions[]` admite un IVA y un ISLR; usar `upload_id` en lugar de attachment y UUID separado por comprobante. Sin control confirmado quedan pendientes. Deshabilitar previsión con `{ "enabled": false }`.

Reversión: `{ "operation_key": "ef018a3e-143b-41db-9803-470b7141ee04", "reason": "Corrección del comprobante recibido" }`. Reintento: `{ "operation_key": "UUID publicado en hka_operation_key" }`. Nunca reutilizar UUID de registro para reversión.

## Matriz de cobertura

| Entrada | Integración |
|---|---|
| Web | Formulario de factura, campos compartidos, opciones/listado/cobros posteriores. |
| POS | Pagos, pagos rápidos, opciones; objetivo neto combinado, conserva abonos. |
| Garage/Vende Ya | Pago rápido Garage y opciones comunes, borradores y reapertura. |
| API/MobileApp | Transform/DocumentInput/guardado y recursos públicos comunes. |
| Hotel | Checkout y opciones de factura resultante. |
| Pedidos | Options de conversión, objetivo neto y medios adicionales con saldo restante. |
| Cotizaciones, notas de venta, órdenes de entrega | Formulario/consumidores comunes al generar la factura; acceso posterior. |
| Restaurante | Facturación POS y gestión posterior. |
| Servicio técnico | Opciones de conversión y persistencia común. |
| Suscription/FullSuscription | Opciones manuales; facturas automáticas con registro posterior. |
| Ecommerce/facturación masiva | Servicio común y registro posterior autorizado; no fabrican comprobantes. |

Las pruebas de consumidores/plantillas y del guardado común cubren el código fuente. La aceptación visual de todas las entradas requiere bundle compilado por el usuario e instalación nueva; no se confunde con esta verificación.

## HKA y aceptación

POST `/api/AplicarRetencionISLR` sobre tipo01 e identidad congelada, `detallesRetencionISLR` con numeroCompRetencion/fechaEmisionCR/impuestoRetenido/rifAgenteRetencion/porcentaje. Omitir codigoOperacion: no existe equivalencia documentada con concept_id. GET y DELETE envían identidad mediante cuerpo JSON. Importes convertidos desde comprobante a moneda fiscal congelada, no tasa actual del catálogo.

[Evidencia DEMO saneada](evidencias/retenciones_islr_demo_2026_10_12.json): controles cobrados VES/USD aceptaron POST/GET/DELETE (200); consulta coincide en los campos publicados. Ausencia ISLR antes/después: HTTP200/código204/mensaje «No se ha registrado aún ninguna Retención de ISLR a este documento», sin validaciones. Eliminar ISLR conservó IVA, verificado por consulta independiente; después se retiraron únicamente las retenciones propias de la prueba. No se guardó clasificación fiscal ficticia del emisor autenticado.

El circuito comercial se probó con formasPago reales: ISLR neto VES (226/232) fue aceptado en esa prueba; ISLR neto USD y combinado IVA+ISLR VES/USD fueron rechazados con 203/1016. El rechazo previo IVA no se utilizó como prueba ISLR. El ciclo VES aceptado también validó el servicio real con previsión 6, cobro 226 y saldo 6: aplicar dejó saldo 0, GET confirmó, DELETE/reversión dejó saldo 6 y caja 226. La aceptación parcial VES no habilita la capacidad: `islr_retention.demo_net_collection_verified=false` y ambas capacidades exigidas para combinación. No se inventaron pagos ni se habilitó producción. Los resultados y el ciclo del caso neto VES se conservan en la evidencia.

## Verificación de esta entrega

- `HkaIslrRetentionTest`: payload/tasa congelada, campos verificados, ausencia204 específica, conflictos e incompletos, GET/DELETE JSON, guardia neto.
- `test_islr_*`: fórmula/sustraendo, independencia SPE, base fiscal/cambio de tasa, coexistencia, UUID/reemplazo/restitución, caja, rollback, pendiente/discrepancia, timeout/recuperación, soporte/permisos, activación fallida y concurrencia.
- Regresiones IVA, pagos/SPE, contratos HTTP/PDF, JavaScript y consolidado/seeding/FKs/rollback/repetición en MySQL temporal.
- Compilación y aceptación visual pendientes según frontend-build. No se compiló ni editaron assets generados.

Resultados ejecutados: 151 pruebas JavaScript; regresiones PHP/PDF sin fallos (los casos de esquema requieren variable MySQL y se ejecutaron separadamente), luego comprobación MySQL real de instalación/rollback/repetición + IVA (8 casos/1959 aserciones). Los seis casos ISLR del trait y la concurrencia existente se verificaron en bases temporales; se corrigieron los fixtures para exigir confirmación fiscal y modalidad local cuando corresponde. El caso rechazo/adopción/conflicto incluye un INSERT directo que acredita la restricción SQL de un ISLR activo. HkaIslrRetentionTest verificó payload, transporte, parsers, guardia neta y validación ordinaria de entradas malformadas tras incorporar el sobre 204 observado. Las advertencias preexistentes de firma opcional Item y formato XML PHPUnit no impiden las pruebas.

Manifest/bundle inspeccionados: compilación anterior a las fuentes ISLR y sin el componente nuevo. Falta compilación del usuario y verificación visual de cada entrada en instalación nueva; no se modificó public/build.

## Actualización de notas del 13/10/2026

El contrato vigente de notas domésticas, devoluciones y ajustes está en [notas de crédito y débito HKA](notas_credito_debito_hka.md). Reemplaza las restricciones históricas de notas vinculadas de esta etapa para el flujo ordinario identificado por UUID de creación. ND cobradas:34 variantes confirmadas/consultadas con PDF real; NC:36 variantes rechazadas por203/1015, con efectos comerciales guardados y capacidad fiscal bloqueada, sin pagos ficticios. Ajustes IGTF sin contrato/aceptación específica conservan pendientes. Esquema sólo para instalaciones nuevas; no se convirtió ningún tenant real.
